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
    private function fetchPhotosByCategory(string $categoryUrl): array
    {
        // Extract category name from URL
        $categoryName = $this->extractCategoryName($categoryUrl);
        if (!$categoryName) {
            return [];
        }

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
        
        if (!isset($data['query']['search'])) {
            return $photos;
        }

        foreach ($data['query']['search'] as $result) {
            $photo = $this->processCommonsPhoto($result);
            if ($photo) {
                $photos[] = $photo;
            }
        }

        return $photos;
    }

    /**
     * Process Commons category results.
     */
    private function processCommonsCategoryResults(array $data): array
    {
        $photos = [];
        
        if (!isset($data['query']['categorymembers'])) {
            return $photos;
        }

        foreach ($data['query']['categorymembers'] as $member) {
            $photo = $this->processCommonsPhoto($member);
            if ($photo) {
                $photos[] = $photo;
            }
        }

        return $photos;
    }

    /**
     * Process a single Commons photo result.
     */
    private function processCommonsPhoto(array $result): ?array
    {
        $title = $result['title'] ?? null;
        if (!$title || !str_starts_with($title, 'File:')) {
            return null;
        }

        $filename = str_replace('File:', '', $title);
        
        return [
            'commons_filename' => $filename,
            'commons_url' => "https://commons.wikimedia.org/wiki/{$title}",
            'thumbnail_url' => $this->buildThumbnailUrl($filename),
            'original_url' => $this->buildOriginalUrl($filename),
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
    private function buildThumbnailUrl(string $filename): string
    {
        $encodedFilename = urlencode($filename);
        return "https://commons.wikimedia.org/w/thumb.php?f={$encodedFilename}&width=300";
    }

    /**
     * Build original URL for a Commons file.
     */
    private function buildOriginalUrl(string $filename): string
    {
        $encodedFilename = urlencode($filename);
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
} 