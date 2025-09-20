<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdatePhotoMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $limit = null,
        public bool $forceUpdate = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting photo metadata update', [
            'limit' => $this->limit,
            'force_update' => $this->forceUpdate,
        ]);

        $query = Photo::query();
        
        // Only update photos that don't have photographer/license info
        if (!$this->forceUpdate) {
            $query->where(function ($q) {
                $q->whereNull('photographer')
                  ->orWhereNull('license')
                  ->orWhere('license', 'Unknown');
            });
        }

        if ($this->limit) {
            $query->limit($this->limit);
        }

        $photos = $query->get();
        $processedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        foreach ($photos as $photo) {
            try {
                $processedCount++;
                
                // Skip if already has metadata and not forcing update
                if (!$this->forceUpdate && $photo->photographer && $photo->license && $photo->license !== 'Unknown') {
                    Log::debug('Skipping photo with existing metadata', [
                        'photo_id' => $photo->id,
                        'photographer' => $photo->photographer,
                        'license' => $photo->license,
                    ]);
                    continue;
                }

                $metadata = $this->fetchPhotoMetadata($photo->commons_filename);
                
                if ($metadata) {
                    $photo->update([
                        'photographer' => $metadata['photographer'],
                        'license' => $metadata['license'],
                        'license_shortname' => $metadata['license_shortname'],
                        'title' => $metadata['title'] ?? $photo->title,
                        'description' => $metadata['description'] ?? $photo->description,
                    ]);
                    
                    $updatedCount++;
                    
                    Log::debug('Updated photo metadata', [
                        'photo_id' => $photo->id,
                        'filename' => $photo->commons_filename,
                        'photographer' => $metadata['photographer'],
                        'license' => $metadata['license'],
                    ]);
                } else {
                    Log::debug('No metadata found for photo', [
                        'photo_id' => $photo->id,
                        'filename' => $photo->commons_filename,
                    ]);
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to update photo metadata', [
                    'photo_id' => $photo->id,
                    'filename' => $photo->commons_filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Photo metadata update completed', [
            'processed' => $processedCount,
            'updated' => $updatedCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Fetch metadata for a photo from Wikimedia Commons.
     */
    private function fetchPhotoMetadata(string $filename): ?array
    {
        try {
            // Remove 'File:' prefix if present
            $cleanFilename = str_replace('File:', '', $filename);
            
            $query = [
                'action' => 'query',
                'format' => 'json',
                'titles' => "File:{$cleanFilename}",
                'prop' => 'imageinfo',
                'iiprop' => 'extmetadata|url',
            ];

            $response = Http::withHeaders([
                'User-Agent' => 'WikiLovesMonumentsTurkey/1.0 (https://wlmtr.org; mailto:info@wlmtr.org)',
            ])->timeout(10)->get('https://commons.wikimedia.org/w/api.php', $query);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $page = $data['query']['pages'][array_key_first($data['query']['pages'])] ?? null;
            
            if (!$page || !isset($page['imageinfo'][0]['extmetadata'])) {
                return null;
            }

            $extmetadata = $page['imageinfo'][0]['extmetadata'];
            
            $photographer = $extmetadata['Artist']['value'] ?? null;
            $licenseShortName = $extmetadata['LicenseShortName']['value'] ?? null;
            $license = $extmetadata['License']['value'] ?? null;
            $title = $extmetadata['ImageDescription']['value'] ?? null;
            $description = $extmetadata['ImageDescription']['value'] ?? null;
            
            // Clean up photographer (strip HTML if present)
            if ($photographer) {
                $photographer = strip_tags($photographer);
            }
            
            // Clean up title/description (strip HTML if present)
            if ($title) {
                $title = strip_tags($title);
            }
            
            if ($description) {
                $description = strip_tags($description);
            }

            return [
                'photographer' => $photographer,
                'license' => $license,
                'license_shortname' => $licenseShortName,
                'title' => $title,
                'description' => $description,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch photo metadata from Commons', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Photo metadata update job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}