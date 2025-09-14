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

class SyncMonumentLocations implements ShouldQueue
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
        Log::info('Starting monument location synchronization');

        $service = new WikidataSparqlService();
        $updatedCount = 0;
        $errorCount = 0;

        // Get monuments without location hierarchy, ordered by last_synced_at
        $monuments = Monument::whereNull('location_hierarchy_tr')
            ->whereNotNull('wikidata_id')
            ->orderBy('last_synced_at')
            ->limit($this->batchSize)
            ->get();

        if ($monuments->isEmpty()) {
            Log::info('No monuments need location synchronization');
            return;
        }

        Log::info("Processing {$monuments->count()} monuments for location sync");

        foreach ($monuments as $monument) {
            try {
                $locationHierarchy = $service->fetchLocationHierarchyString($monument->wikidata_id);
                
                if (!empty($locationHierarchy)) {
                    $monument->update(['location_hierarchy_tr' => $locationHierarchy]);
                    $updatedCount++;
                    Log::debug("Updated location for monument {$monument->id}: {$locationHierarchy}");
                } else {
                    Log::debug("No location hierarchy found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                Log::warning("Failed to sync location for monument {$monument->id}: {$e->getMessage()}");
            }
        }

        Log::info("Location sync completed: {$updatedCount} updated, {$errorCount} errors");

        // If we processed a full batch, dispatch another job to continue
        if ($monuments->count() === $this->batchSize) {
            self::dispatch()->delay(now()->addMinutes(5));
            Log::info('Dispatched next location sync job');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Monument location sync job failed: {$exception->getMessage()}");
    }
}