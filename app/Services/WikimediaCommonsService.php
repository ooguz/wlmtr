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

    /**
     * Get comprehensive user information from Commons.
     */
    public function getUserInfo(string $username): ?array
    {
        $query = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'users',
            'ususers' => $username,
            'usprop' => 'blockinfo|editcount|registration|groups|rights|uploadcount',
        ];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::COMMONS_API_ENDPOINT, $query);

            if (! $response->successful()) {
                Log::warning('Failed to fetch user info', [
                    'username' => $username,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (isset($data['query']['users'][0])) {
                $userInfo = $data['query']['users'][0];

                return [
                    'userid' => $userInfo['userid'] ?? null,
                    'name' => $userInfo['name'] ?? $username,
                    'editcount' => $userInfo['editcount'] ?? 0,
                    'registration' => $userInfo['registration'] ?? null,
                    'groups' => $userInfo['groups'] ?? [],
                    'rights' => $userInfo['rights'] ?? [],
                    'uploadcount' => $userInfo['uploadcount'] ?? 0,
                    'blocked' => isset($userInfo['blockid']),
                    'blockinfo' => isset($userInfo['blockid']) ? [
                        'blockid' => $userInfo['blockid'],
                        'blockedby' => $userInfo['blockedby'] ?? null,
                        'blockreason' => $userInfo['blockreason'] ?? null,
                        'blockexpiry' => $userInfo['blockexpiry'] ?? null,
                    ] : null,
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch user info', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Upload a photo to Wikimedia Commons.
     */
    public function uploadPhoto(
        string $accessToken,
        \App\Models\User $user,
        \Illuminate\Http\UploadedFile $photo,
        string $title,
        string $description,
        string $date,
        array $categories,
        \App\Models\Monument $monument
    ): array {
        try {
            // First, get a CSRF token
            $csrfToken = $this->getCSRFToken($accessToken);
            if (! $csrfToken) {
                Log::error('Could not get CSRF token for upload', [
                    'user_id' => $user->id,
                    'has_token' => ! empty($accessToken),
                ]);

                return [
                    'success' => false,
                    'error' => 'CSRF token alınamadı. Lütfen çıkış yapıp tekrar giriş yapın.',
                ];
            }

            // Prepare filename
            $filename = $this->prepareFilename($title, $photo->getClientOriginalExtension());

            // Prepare wikitext
            $wikitext = $this->prepareWikitext($description, $date, $categories, $monument, $user);

            // Upload the file
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Authorization' => 'Bearer '.$accessToken,
            ])
                ->attach('file', file_get_contents($photo->getRealPath()), $filename)
                ->post(self::COMMONS_API_ENDPOINT, [
                    'action' => 'upload',
                    'format' => 'json',
                    'filename' => $filename,
                    'text' => $wikitext,
                    'comment' => 'Uploaded via vikianitlariseviyor.tr (WLM-tr) Quick Upload',
                    'token' => $csrfToken,
                    'ignorewarnings' => 1,
                ]);

            if (! $response->successful()) {
                Log::error('Commons upload failed - HTTP error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'user_id' => $user->id,
                ]);

                return [
                    'success' => false,
                    'error' => 'Yükleme başarısız oldu. HTTP Hata: '.$response->status(),
                ];
            }

            $data = $response->json();

            Log::info('Commons upload response', [
                'data' => $data,
                'user_id' => $user->id,
            ]);

            if (isset($data['upload']['result']) && $data['upload']['result'] === 'Success') {
                return [
                    'success' => true,
                    'filename' => $data['upload']['filename'],
                    'url' => $data['upload']['imageinfo']['url'] ?? null,
                    'descriptionurl' => $data['upload']['imageinfo']['descriptionurl'] ?? null,
                ];
            }

            $errorMessage = 'Bilinmeyen hata.';
            if (isset($data['error'])) {
                $errorMessage = $data['error']['info'] ?? $data['error']['code'] ?? $errorMessage;
            } elseif (isset($data['upload']['warnings'])) {
                $warnings = array_values($data['upload']['warnings']);
                $errorMessage = 'Uyarı: '.($warnings[0] ?? 'Dosya yüklenemedi');
            }

            Log::error('Commons upload failed - API error', [
                'error_data' => $data,
                'user_id' => $user->id,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('Photo upload exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Yükleme sırasında bir hata oluştu.',
            ];
        }
    }

    /**
     * Get CSRF token for Commons API.
     */
    private function getCSRFToken(string $accessToken): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Authorization' => 'Bearer '.$accessToken,
            ])->get(self::COMMONS_API_ENDPOINT, [
                'action' => 'query',
                'meta' => 'tokens',
                'type' => 'csrf',
                'format' => 'json',
                'formatversion' => '2',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('CSRF Token Response', [
                    'status' => $response->status(),
                    'has_data' => ! empty($data),
                    'has_query' => isset($data['query']),
                    'has_tokens' => isset($data['query']['tokens']),
                    'has_csrf' => isset($data['query']['tokens']['csrftoken']),
                    'token_preview' => isset($data['query']['tokens']['csrftoken']) ? substr($data['query']['tokens']['csrftoken'], 0, 10).'...' : null,
                    'full_response' => $data,
                ]);

                return $data['query']['tokens']['csrftoken'] ?? null;
            }

            Log::error('Failed to get CSRF token - response not successful', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get CSRF token - exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Prepare filename for Commons.
     */
    private function prepareFilename(string $title, string $extension): string
    {
        // Remove invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9\s\-_.]/', '', $title);
        $filename = trim($filename);

        // Add timestamp to make it unique
        $filename .= '_'.time();

        // Add extension
        if (! str_ends_with(strtolower($filename), '.'.strtolower($extension))) {
            $filename .= '.'.$extension;
        }

        return $filename;
    }

    /**
     * Prepare wikitext for the file description page.
     */
    private function prepareWikitext(
        string $description,
        string $date,
        array $categories,
        \App\Models\Monument $monument,
        \App\Models\User $user
    ): string {
        $wikitext = "== {{int:filedesc}} ==\n";
        $wikitext .= "{{Information\n";

        // Prepare description with Wikidata and WLM templates
        $descriptionText = $description;

        $wikitext .= "|description={{tr|1={$descriptionText}}}\n";
        $wikitext .= "|date={$date}\n";
        $wikitext .= "|source={{own}}\n";
        $wikitext .= "|author=[[User:{$user->wikimedia_username}|{$user->wikimedia_username}]]\n";
        $wikitext .= "}}\n\n";

        // Add location template if coordinates available
        if ($monument->hasCoordinates()) {
            $wikitext .= "{{Location|{$monument->latitude}|{$monument->longitude}}}\n\n";
        }

        // Add license
        $wikitext .= "== {{int:license-header}} ==\n";
        $wikitext .= "{{self|cc-by-sa-4.0}}\n\n";

        // Add Wiki Loves Monuments template (required)
        $wikitext .= "{{Wiki Loves Monuments 2025|tr}}\n\n";

        // Add categories
        if (! empty($categories)) {
            foreach ($categories as $category) {
                $wikitext .= "[[Category:{$category}]]\n";
            }
        }

        // Add campaign category (required)
        $wikitext .= "[[Category:Uploaded via Campaign:wlm-tr]]\n";

        return $wikitext;
    }
}
