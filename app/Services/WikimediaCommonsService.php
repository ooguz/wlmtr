<?php

namespace App\Services;

use App\Models\Monument;
use App\Models\Photo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikimediaCommonsService
{
    private const COMMONS_API_ENDPOINT = 'https://commons.wikimedia.org/w/api.php';

    private const USER_AGENT = 'WikiLovesMonumentsTurkey/1.0 (https://wlmtr.org; mailto:info@wlmtr.org)';

    /**
     * Fetch photos for a monument from Wikimedia Commons.
     */
    public function fetchPhotosForMonument(Monument $monument): array
    {
        $photos = [];

        // Try to fetch photos using the monument's Wikidata ID
        if ($monument->wikidata_id) {
            $photos = array_merge($photos, $this->fetchPhotosByWikidataId($monument->wikidata_id));
        }

        // Try to fetch photos using Commons category if available
        if ($monument->commons_url) {
            $photos = array_merge($photos, $this->fetchPhotosByCategory($monument->commons_url));
        }

        return $photos;
    }

    /**
     * Fetch photos using Wikidata ID.
     */
    private function fetchPhotosByWikidataId(string $wikidataId): array
    {
        $query = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'search',
            'srsearch' => "haswbstatement:P180={$wikidataId}",
            'srnamespace' => 6, // File namespace
            'srlimit' => 50,
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processCommonsSearchResults($data);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Commons photos by Wikidata ID', [
                'wikidata_id' => $wikidataId,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Fetch photos using Commons category.
     */
    public function fetchPhotosByCategory(string $categoryUrl): array
    {
        // Extract category name from URL
        $categoryName = $this->extractCategoryName($categoryUrl);
        if (! $categoryName) {
            return [];
        }

        // First, get the list of files in the category
        $query = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'categorymembers',
            'cmtitle' => "Category:{$categoryName}",
            'cmnamespace' => 6, // File namespace
            'cmlimit' => 50,
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processCommonsCategoryResults($data);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Commons photos by category', [
                'category' => $categoryName,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Process Commons search results.
     */
    private function processCommonsSearchResults(array $data): array
    {
        $photos = [];

        if (! isset($data['query']['search'])) {
            return $photos;
        }

        // Get the list of files first
        $files = [];
        foreach ($data['query']['search'] as $result) {
            if (str_starts_with($result['title'], 'File:')) {
                $files[] = $result['title'];
            }
        }

        // Now fetch image info for all files in one API call
        if (! empty($files)) {
            $photos = $this->fetchImageInfoForFiles($files);
        }

        return $photos;
    }

    /**
     * Process Commons category results.
     */
    private function processCommonsCategoryResults(array $data): array
    {
        $photos = [];

        if (! isset($data['query']['categorymembers'])) {
            return $photos;
        }

        // Get the list of files first
        $files = [];
        foreach ($data['query']['categorymembers'] as $member) {
            if (str_starts_with($member['title'], 'File:')) {
                $files[] = $member['title'];
            }
        }

        // Now fetch image info for all files in one API call
        if (! empty($files)) {
            $photos = $this->fetchImageInfoForFiles($files);
        }

        return $photos;
    }

    /**
     * Fetch image info for multiple files.
     */
    public function fetchImageInfoForFiles(array $files): array
    {
        $photos = [];
        $titles = implode('|', $files);

        $query = [
            'action' => 'query',
            'format' => 'json',
            'titles' => $titles,
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime|extmetadata',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['query']['pages'])) {
                    foreach ($data['query']['pages'] as $page) {
                        $photo = $this->processCommonsPhotoWithImageInfo($page);
                        if ($photo) {
                            $photos[] = $photo;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch image info for files', [
                'error' => $e->getMessage(),
            ]);
        }

        return $photos;
    }

    /**
     * Process a single Commons photo with image info.
     */
    private function processCommonsPhotoWithImageInfo(array $page): ?array
    {
        $title = $page['title'] ?? null;
        if (! $title || ! str_starts_with($title, 'File:')) {
            return null;
        }

        $filename = str_replace('File:', '', $title);

        // Get the actual file URL from the API response
        $originalUrl = null;
        if (isset($page['imageinfo'][0]['url'])) {
            $originalUrl = $page['imageinfo'][0]['url'];
        } else {
            // Fallback to constructed URL
            $originalUrl = $this->buildOriginalUrl($filename);
        }

        // Get extmetadata
        $extmetadata = $page['imageinfo'][0]['extmetadata'] ?? [];
        $author = $extmetadata['Artist']['value'] ?? null;
        $licenseShortName = $extmetadata['LicenseShortName']['value'] ?? null;
        $license = $extmetadata['License']['value'] ?? null;

        // Clean up author (strip HTML if present)
        if ($author) {
            $author = strip_tags($author);
        }

        return [
            'commons_filename' => $filename,
            'commons_url' => "https://commons.wikimedia.org/wiki/{$title}",
            'thumbnail_url' => $this->buildThumbnailUrl($filename),
            'original_url' => $originalUrl,
            'title' => $filename,
            'description' => null,
            'photographer' => $author,
            'license' => $license,
            'license_shortname' => $licenseShortName,
            'is_featured' => false,
            'is_uploaded_via_app' => false,
        ];
    }

    /**
     * Process a single Commons photo result.
     */
    private function processCommonsPhoto(array $result): ?array
    {
        $title = $result['title'] ?? null;
        if (! $title || ! str_starts_with($title, 'File:')) {
            return null;
        }

        $filename = str_replace('File:', '', $title);

        // Get the actual file URL from the API response
        $originalUrl = null;
        if (isset($result['imageinfo'][0]['url'])) {
            $originalUrl = $result['imageinfo'][0]['url'];
        } else {
            // Fallback to constructed URL
            $originalUrl = $this->buildOriginalUrl($filename);
        }

        return [
            'commons_filename' => $filename,
            'commons_url' => "https://commons.wikimedia.org/wiki/{$title}",
            'thumbnail_url' => $this->buildThumbnailUrl($filename),
            'original_url' => $originalUrl,
            'title' => $filename,
            'description' => null,
            'photographer' => null,
            'license' => null,
            'is_featured' => false,
            'is_uploaded_via_app' => false,
        ];
    }

    /**
     * Build thumbnail URL for a Commons file.
     */
    public function buildThumbnailUrl(string $filename): string
    {
        $filename = str_replace(' ', '_', $filename);
        $encodedFilename = rawurlencode($filename);

        return "https://commons.wikimedia.org/w/thumb.php?f={$encodedFilename}&width=300";
    }

    /**
     * Build original URL for a Commons file.
     * Note: This is a fallback method. The actual URL should come from the API response.
     */
    public function buildOriginalUrl(string $filename): string
    {
        // Use the Commons API to get the actual file URL
        $apiUrl = 'https://commons.wikimedia.org/w/api.php';
        $params = [
            'action' => 'query',
            'titles' => "File:{$filename}",
            'prop' => 'imageinfo',
            'iiprop' => 'url',
            'format' => 'json',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get($apiUrl, $params);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['query']['pages'])) {
                    foreach ($data['query']['pages'] as $page) {
                        if (isset($page['imageinfo'][0]['url'])) {
                            return $page['imageinfo'][0]['url'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to get original URL for file', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to constructed URL (may not work for all files)
        $filename = str_replace(' ', '_', $filename);
        $encodedFilename = rawurlencode($filename);

        return "https://upload.wikimedia.org/wikipedia/commons/{$encodedFilename}";
    }

    /**
     * Extract category name from Commons URL.
     */
    private function extractCategoryName(string $url): ?string
    {
        if (preg_match('/Category:(.+)$/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Sync photos for all monuments.
     */
    public function syncPhotosForAllMonuments(): int
    {
        $monuments = Monument::whereNotNull('wikidata_id')->get();
        $totalPhotos = 0;

        foreach ($monuments as $monument) {
            try {
                $photos = $this->fetchPhotosForMonument($monument);
                $photoCount = $this->savePhotosForMonument($monument, $photos);
                $totalPhotos += $photoCount;

                // Update monument's photo count
                $monument->update([
                    'photo_count' => $photoCount,
                    'has_photos' => $photoCount > 0,
                ]);

                Log::info('Synced photos for monument', [
                    'monument_id' => $monument->id,
                    'wikidata_id' => $monument->wikidata_id,
                    'photo_count' => $photoCount,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to sync photos for monument', [
                    'monument_id' => $monument->id,
                    'wikidata_id' => $monument->wikidata_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalPhotos;
    }

    /**
     * Save photos for a monument.
     */
    private function savePhotosForMonument(Monument $monument, array $photos): int
    {
        $savedCount = 0;

        foreach ($photos as $photoData) {
            try {
                Photo::updateOrCreate(
                    [
                        'monument_id' => $monument->id,
                        'commons_filename' => $photoData['commons_filename'],
                    ],
                    $photoData
                );
                $savedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to save photo', [
                    'monument_id' => $monument->id,
                    'filename' => $photoData['commons_filename'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $savedCount;
    }

    /**
     * Fetch user's upload statistics and recent files from Commons.
     */
    public function getUserUploads(string $username, int $limit = 20): array
    {
        $query = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'allimages',
            'aiuser' => $username,
            'ailimit' => $limit,
            'aiprop' => 'timestamp|url|size|mime|user|comment|extmetadata',
            'aisort' => 'timestamp',
            'aidir' => 'descending',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if (! $response->successful()) {
                Log::warning('Failed to fetch user uploads', [
                    'username' => $username,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();
            $files = [];

            if (isset($data['query']['allimages'])) {
                foreach ($data['query']['allimages'] as $image) {
                    $files[] = [
                        'name' => $image['name'] ?? null,
                        'url' => $image['url'] ?? null,
                        'thumb_url' => $image['thumburl'] ?? null,
                        'description_url' => $image['descriptionurl'] ?? null,
                        'timestamp' => $image['timestamp'] ?? null,
                        'size' => $image['size'] ?? 0,
                        'width' => $image['width'] ?? 0,
                        'height' => $image['height'] ?? 0,
                        'mime' => $image['mime'] ?? null,
                        'comment' => $image['comment'] ?? null,
                    ];
                }
            }

            return $files;
        } catch (\Exception $e) {
            Log::error('Failed to fetch user uploads', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get user's upload count from Commons.
     */
    public function getUserUploadCount(string $username): int
    {
        $query = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'users',
            'ususers' => $username,
            'usprop' => 'uploadcount',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if (! $response->successful()) {
                return 0;
            }

            $data = $response->json();

            if (isset($data['query']['users'][0]['uploadcount'])) {
                return (int) $data['query']['users'][0]['uploadcount'];
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Failed to fetch user upload count', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
