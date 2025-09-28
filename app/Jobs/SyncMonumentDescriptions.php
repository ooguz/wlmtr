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
        $lock = \Cache::lock('jobs:sync-monument-descriptions', 600);
        if (! $lock->get()) {
            return;
        }
        try {
        Log::info('Starting monument detailed data synchronization');

        $service = new WikidataSparqlService();
        $updatedCount = 0;
        $errorCount = 0;

        // Get monuments without descriptions, aliases, or other missing fields
        $monuments = Monument::where(function ($query) {
                $query->whereNull('description_tr')
                    ->orWhereNull('description_en')
                    ->orWhereNull('aka')
                    ->orWhereNull('kulturenvanteri_id')
                    ->orWhereNull('commons_category');
            })
            ->whereNotNull('wikidata_id')
            ->orderBy('last_synced_at')
            ->limit($this->batchSize)
            ->get();

        if ($monuments->isEmpty()) {
            Log::info('No monuments need detailed data synchronization');
            return;
        }

        Log::info("Processing {$monuments->count()} monuments for detailed data sync");

        foreach ($monuments as $monument) {
            try {
                $entityData = $service->getEntityData($monument->wikidata_id);
                
                if ($entityData) {
                    $updateData = [];
                    
                    // Extract descriptions
                    if (isset($entityData['descriptions'])) {
                        $descriptions = $entityData['descriptions'];
                        
                        // Extract Turkish description
                        if (isset($descriptions['tr']['value'])) {
                            $updateData['description_tr'] = $descriptions['tr']['value'];
                        }
                        
                        // Extract English description
                        if (isset($descriptions['en']['value'])) {
                            $updateData['description_en'] = $descriptions['en']['value'];
                        }
                    }
                    
                    // Extract aliases (TR)
                    if (isset($entityData['aliases']['tr'])) {
                        $aliasesTr = [];
                        foreach ($entityData['aliases']['tr'] as $alias) {
                            if (isset($alias['value'])) {
                                $aliasesTr[] = (string) $alias['value'];
                            }
                        }
                        if (!empty($aliasesTr)) {
                            $updateData['aka'] = implode(', ', $aliasesTr);
                        }
                    }
                    
                    // Extract P11729 (Kültür Envanteri ID)
                    if (isset($entityData['claims']['P11729'])) {
                        foreach ($entityData['claims']['P11729'] as $claim) {
                            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
                            if (is_string($value)) {
                                $updateData['kulturenvanteri_id'] = $value;
                                break;
                            }
                        }
                    }
                    
                    // Extract P373 (Commons category)
                    if (isset($entityData['claims']['P373'])) {
                        foreach ($entityData['claims']['P373'] as $claim) {
                            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
                            if (is_string($value)) {
                                $updateData['commons_category'] = $value;
                                break;
                            }
                        }
                    }
                    
                    if (!empty($updateData)) {
                        $monument->update($updateData);
                        $updatedCount++;
                        
                        $updateInfo = [];
                        if (isset($updateData['description_tr'])) {
                            $updateInfo[] = "TR desc: " . substr($updateData['description_tr'], 0, 30) . "...";
                        }
                        if (isset($updateData['description_en'])) {
                            $updateInfo[] = "EN desc: " . substr($updateData['description_en'], 0, 30) . "...";
                        }
                        if (isset($updateData['aka'])) {
                            $updateInfo[] = "AKA: " . substr($updateData['aka'], 0, 30) . "...";
                        }
                        if (isset($updateData['kulturenvanteri_id'])) {
                            $updateInfo[] = "KE ID: " . $updateData['kulturenvanteri_id'];
                        }
                        if (isset($updateData['commons_category'])) {
                            $updateInfo[] = "Commons: " . $updateData['commons_category'];
                        }
                        
                        Log::debug("Updated monument {$monument->id}: " . implode(', ', $updateInfo));
                    } else {
                        Log::debug("No new data found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                    }
                } else {
                    Log::debug("No entity data found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                Log::warning("Failed to sync detailed data for monument {$monument->id}: {$e->getMessage()}");
            }
        }

        Log::info("Detailed data sync completed: {$updatedCount} updated, {$errorCount} errors");

        // If we processed a full batch, dispatch another job to continue
        if ($monuments->count() === $this->batchSize) {
            self::dispatch()->delay(now()->addMinutes(10));
            Log::info('Dispatched next detailed data sync job');
        }
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Monument detailed data sync job failed: {$exception->getMessage()}");
    }
}