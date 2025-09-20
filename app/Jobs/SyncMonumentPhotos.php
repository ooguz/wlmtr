<?php

namespace App\Jobs;

use App\Models\Monument;
use App\Models\Photo;
use App\Services\WikidataSparqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMonumentPhotos implements ShouldQueue
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
        Log::info('Starting monument photos sync', [
            'limit' => $this->limit,
            'force_update' => $this->forceUpdate,
        ]);

        $sparqlService = new WikidataSparqlService();
        $monumentsWithImages = $sparqlService->fetchAllMonumentsWithImages($this->limit);

        $processedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        foreach ($monumentsWithImages as $monumentData) {
            try {
                $processedCount++;
                
                // Find existing monument by Wikidata ID
                $monument = Monument::where('wikidata_id', $monumentData['wikidata_id'])->first();
                
                if (!$monument) {
                    Log::warning('Monument not found in database', [
                        'wikidata_id' => $monumentData['wikidata_id'],
                        'name' => $monumentData['name'],
                    ]);
                    continue;
                }

                // Check if we should skip this monument
                if (!$this->forceUpdate && $monument->has_photos && $monument->photos()->count() > 0) {
                    Log::debug('Skipping monument with existing photos', [
                        'wikidata_id' => $monument->wikidata_id,
                        'existing_photos' => $monument->photos()->count(),
                    ]);
                    continue;
                }

                // Update monument photo status
                $monument->update([
                    'has_photos' => true,
                    'photo_count' => count($monumentData['images']),
                    'last_synced_at' => now(),
                ]);

                // Clear existing photos if force update
                if ($this->forceUpdate) {
                    $monument->photos()->delete();
                }

                // Add photos to database
                foreach ($monumentData['images'] as $index => $imageData) {
                    Photo::create([
                        'monument_id' => $monument->id,
                        'commons_filename' => $imageData['filename'],
                        'commons_url' => $imageData['urls']['commons_page'],
                        'thumbnail_url' => $imageData['urls']['thumbnail'],
                        'original_url' => $imageData['urls']['original'],
                        'title' => $imageData['title'] ?? $monument->primary_name,
                        'description' => $imageData['description'] ?? null,
                        'photographer' => $imageData['photographer'] ?? null,
                        'license' => $imageData['license'] ?? null,
                        'license_shortname' => $imageData['license_shortname'] ?? null,
                        'date_taken' => $imageData['date_taken'] ?? null,
                        'is_featured' => $index === 0, // First image is featured
                        'is_uploaded_via_app' => false,
                        'uploaded_at' => now(),
                    ]);
                }

                $updatedCount++;
                
                Log::debug('Updated monument photos', [
                    'wikidata_id' => $monument->wikidata_id,
                    'name' => $monument->primary_name,
                    'photos_added' => count($monumentData['images']),
                ]);

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to sync monument photos', [
                    'wikidata_id' => $monumentData['wikidata_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Monument photos sync completed', [
            'processed' => $processedCount,
            'updated' => $updatedCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Monument photos sync job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}