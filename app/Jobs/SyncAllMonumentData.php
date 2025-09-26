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

class SyncAllMonumentData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    /**
     * Number of monuments to process in each batch.
     */
    public int $batchSize = 25;

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
        Log::info('Starting comprehensive monument data synchronization');

        $service = new WikidataSparqlService();
        $updatedCount = 0;
        $errorCount = 0;

        // Get monuments that need updates (missing location, descriptions, or other fields)
        $monuments = Monument::where(function ($query) {
                $query->whereNull('location_hierarchy_tr')
                      ->orWhereNull('description_tr')
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
            Log::info('No monuments need comprehensive data synchronization');
            return;
        }

        Log::info("Processing {$monuments->count()} monuments for comprehensive sync");

        foreach ($monuments as $monument) {
            try {
                $entityData = $service->getEntityData($monument->wikidata_id);
                
                if (!$entityData) {
                    Log::debug("No entity data found for monument {$monument->id} (Wikidata: {$monument->wikidata_id})");
                    continue;
                }

                $updateData = [];
                $hasUpdates = false;

                // Update location hierarchy if missing
                if (empty($monument->location_hierarchy_tr)) {
                    $locationHierarchy = $service->fetchLocationHierarchyString($monument->wikidata_id);
                    if (!empty($locationHierarchy)) {
                        $updateData['location_hierarchy_tr'] = $locationHierarchy;
                        $hasUpdates = true;
                    }
                }

                // Update descriptions if missing
                if (isset($entityData['descriptions'])) {
                    $descriptions = $entityData['descriptions'];
                    
                    if (empty($monument->description_tr) && isset($descriptions['tr']['value'])) {
                        $updateData['description_tr'] = $descriptions['tr']['value'];
                        $hasUpdates = true;
                    }
                    
                    if (empty($monument->description_en) && isset($descriptions['en']['value'])) {
                        $updateData['description_en'] = $descriptions['en']['value'];
                        $hasUpdates = true;
                    }
                }

                // Update aliases if missing
                if (empty($monument->aka) && isset($entityData['aliases']['tr'])) {
                    $aliasesTr = [];
                    foreach ($entityData['aliases']['tr'] as $alias) {
                        if (isset($alias['value'])) {
                            $aliasesTr[] = (string) $alias['value'];
                        }
                    }
                    if (!empty($aliasesTr)) {
                        $updateData['aka'] = implode(', ', $aliasesTr);
                        $hasUpdates = true;
                    }
                }

                // Update Kültür Envanteri ID if missing
                if (empty($monument->kulturenvanteri_id) && isset($entityData['claims']['P11729'])) {
                    foreach ($entityData['claims']['P11729'] as $claim) {
                        $value = $claim['mainsnak']['datavalue']['value'] ?? null;
                        if (is_string($value)) {
                            $updateData['kulturenvanteri_id'] = $value;
                            $hasUpdates = true;
                            break;
                        }
                    }
                }

                // Update Commons category if missing
                if (empty($monument->commons_category) && isset($entityData['claims']['P373'])) {
                    foreach ($entityData['claims']['P373'] as $claim) {
                        $value = $claim['mainsnak']['datavalue']['value'] ?? null;
                        if (is_string($value)) {
                            $updateData['commons_category'] = $value;
                            $hasUpdates = true;
                            break;
                        }
                    }
                }

                // Update last_synced_at
                $updateData['last_synced_at'] = now();

                if ($hasUpdates) {
                    $monument->update($updateData);
                    $updatedCount++;
                    
                    $updateInfo = [];
                    if (isset($updateData['location_hierarchy_tr'])) {
                        $updateInfo[] = "Location: " . substr($updateData['location_hierarchy_tr'], 0, 30) . "...";
                    }
                    if (isset($updateData['description_tr'])) {
                        $updateInfo[] = "TR Desc: " . substr($updateData['description_tr'], 0, 30) . "...";
                    }
                    if (isset($updateData['description_en'])) {
                        $updateInfo[] = "EN Desc: " . substr($updateData['description_en'], 0, 30) . "...";
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
                    // Still update last_synced_at even if no data changes
                    $monument->update(['last_synced_at' => now()]);
                }
            } catch (\Throwable $e) {
                $errorCount++;
                Log::warning("Failed to sync data for monument {$monument->id}: {$e->getMessage()}");
            }
        }

        Log::info("Comprehensive sync completed: {$updatedCount} updated, {$errorCount} errors");

        // If we processed a full batch, dispatch another job to continue
        if ($monuments->count() === $this->batchSize) {
            self::dispatch()->delay(now()->addMinutes(15));
            Log::info('Dispatched next comprehensive sync job');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Comprehensive monument data sync job failed: {$exception->getMessage()}");
    }
}