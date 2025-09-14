<?php

namespace App\Jobs;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMonumentDescriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Number of monuments to process in each batch.
     */
    public int $batchSize = 50;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting monument description synchronization');

        $service = new WikidataSparqlService();
        $updatedCount = 0;
        $errorCount = 0;

        // Get monuments without descriptions, ordered by last_synced_at
        $monuments = Monument::whereNull('description_tr')
            ->whereNull('description_en')
            ->whereNotNull('wikidata_id')
            ->orderBy('last_synced_at')
            ->limit($this->batchSize)
            ->get();

        if ($monuments->isEmpty()) {
            Log::info('No monuments need description synchronization');
            return;
        }

        Log::info("Processing {$monuments->count()} monuments for description sync");

        foreach ($monuments as $monument) {
            try {
                $entityData = $service->getEntityData($monument->wikidata_id);
                
                if ($entityData && isset($entityData['descriptions'])) {
                    $descriptions = $entityData['descriptions'];
                    
                    $updateData = [];
                    
                    // Extract Turkish description
                    if (isset($descriptions['tr']['value'])) {
                        $updateData['description_tr'] = $descriptions['tr']['value'];
                    }
                    
                    // Extract English description
                    if (isset($descriptions['en']['value'])) {
                        $updateData['description_en'] = $descriptions['en']['value'];
                    }
                    
                    if (!empty($updateData)) {
                        $monument->update($updateData);
                        $updatedCount++;
                        
                        $descInfo = [];
                        if (isset($updateData['description_tr'])) {
                            $descInfo[] = "TR: " . substr($updateData['description_tr'], 0, 50) . "...";
                        }
                        if (isset($updateData['description_en'])) {
                            $descInfo[] = "EN: " . substr($updateData['description_en'], 0, 50) . "...";
                        }
                        
                        Log::debug("Updated descriptions for monument {$monument->id}: " . implode(', ', $descInfo));
                    } else {
                        Log::debug("No descriptions found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                    }
                } else {
                    Log::debug("No entity data found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                Log::warning("Failed to sync descriptions for monument {$monument->id}: {$e->getMessage()}");
            }
        }

        Log::info("Description sync completed: {$updatedCount} updated, {$errorCount} errors");

        // If we processed a full batch, dispatch another job to continue
        if ($monuments->count() === $this->batchSize) {
            self::dispatch()->delay(now()->addMinutes(10));
            Log::info('Dispatched next description sync job');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Monument description sync job failed: {$exception->getMessage()}");
    }
}