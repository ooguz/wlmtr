<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HydrateMonumentsBulk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:hydrate-bulk 
                            {--batch-size=50 : Number of monuments to process per batch}
                            {--limit=1000 : Maximum number of monuments to process}
                            {--update-existing : Update all monuments instead of only missing fields}
                            {--delay=1000 : Delay between batches in milliseconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hydrate monument fields in bulk using Wikidata batch API for better performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $limit = (int) $this->option('limit');
        $updateExisting = $this->option('update-existing');
        $delay = (int) $this->option('delay');

        $this->info("Starting bulk hydration with batch size: {$batchSize}, limit: {$limit}");

        $query = Monument::query();
        if ($updateExisting) {
            $this->info('Updating all monuments (--update-existing flag set)');
        } else {
            // Only update monuments with missing fields
            $query->where(function ($q) {
                $q->whereNull('description_tr')
                    ->orWhereNull('aka')
                    ->orWhereNull('kulturenvanteri_id')
                    ->orWhereNull('commons_category')
                    ->orWhereNull('city')
                    ->orWhereNull('province');
            });
        }

        $totalMonuments = $query->count();
        $this->info("Found {$totalMonuments} monuments to process");

        if ($totalMonuments === 0) {
            $this->info('No monuments need hydration');
            return 0;
        }

        $processed = 0;
        $updated = 0;
        $failed = 0;
        $batchNumber = 1;

        // Process monuments in batches
        $query->chunk($batchSize, function ($monuments) use (&$processed, &$updated, &$failed, &$batchNumber, $delay, $limit) {
            if ($processed >= $limit) {
                return false; // Stop processing if limit reached
            }

            $this->info("Processing batch {$batchNumber} ({$monuments->count()} monuments)");

            // Extract Wikidata IDs for this batch
            $wikidataIds = $monuments->pluck('wikidata_id')->filter()->toArray();
            
            if (empty($wikidataIds)) {
                $this->warn("No valid Wikidata IDs in batch {$batchNumber}");
                $processed += $monuments->count();
                $batchNumber++;
                return true;
            }

            // Fetch entity data for all monuments in this batch
            $entitiesData = $this->fetchBulkEntityData($wikidataIds);
            
            if (empty($entitiesData)) {
                $this->warn("No entity data received for batch {$batchNumber}");
                $processed += $monuments->count();
                $batchNumber++;
                return true;
            }

            // Process each monument in the batch
            foreach ($monuments as $monument) {
                try {
                    if (!$monument->wikidata_id) {
                        continue;
                    }

                    $entityData = $entitiesData[$monument->wikidata_id] ?? null;
                    if (!$entityData) {
                        $this->warn("No entity data for {$monument->wikidata_id}");
                        $failed++;
                        continue;
                    }

                    $updates = $this->mapEntityToFields($entityData);
                    if (empty($updates)) {
                        continue;
                    }

                    // Merge properties
                    if (isset($updates['properties'])) {
                        $existing = $monument->properties ?? [];
                        if (is_string($existing)) {
                            $decoded = json_decode($existing, true);
                            $existing = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                        }
                        $updates['properties'] = array_filter(array_merge($existing ?: [], $updates['properties']));
                    }

                    $monument->fill($updates);
                    $monument->last_synced_at = now();
                    $monument->save();
                    $updated++;
                    
                    $this->line("✓ {$monument->wikidata_id} updated");

                } catch (\Throwable $e) {
                    Log::error('Bulk hydration failed', [
                        'wikidata_id' => $monument->wikidata_id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("! Failed {$monument->wikidata_id}: {$e->getMessage()}");
                    $failed++;
                }
            }

            $processed += $monuments->count();
            $batchNumber++;

            // Add delay between batches to avoid overwhelming the API
            if ($delay > 0) {
                usleep($delay * 1000); // Convert milliseconds to microseconds
            }

            // Continue processing if we haven't reached the limit
            return $processed < $limit;
        });

        $this->info("Bulk hydration completed!");
        $this->info("Processed: {$processed} monuments");
        $this->info("Updated: {$updated} monuments");
        $this->info("Failed: {$failed} monuments");

        return 0;
    }

    /**
     * Fetch entity data for multiple Wikidata IDs using concurrent requests.
     */
    private function fetchBulkEntityData(array $wikidataIds): array
    {
        if (empty($wikidataIds)) {
            return [];
        }

        $allEntities = [];
        $chunkSize = 10; // Process 10 entities at a time
        $chunks = array_chunk($wikidataIds, $chunkSize);

        foreach ($chunks as $chunk) {
            $this->info("Fetching data for " . count($chunk) . " entities...");
            
            // Process each entity in the chunk
            foreach ($chunk as $wikidataId) {
                try {
                    $entityData = WikidataSparqlService::getEntityData($wikidataId);
                    if ($entityData) {
                        $allEntities[$wikidataId] = $entityData;
                    } else {
                        // If we got rate limited, wait a bit before continuing
                        usleep(200000); // 0.2 seconds
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch entity {$wikidataId}: {$e->getMessage()}");
                }
                
                // Small delay between individual requests to avoid rate limiting
                usleep(100000); // 0.1 seconds
            }

            // Small delay between chunks to be respectful to the API
            usleep(500000); // 0.5 seconds
        }

        return $allEntities;
    }

    /**
     * Map Wikidata entity JSON to monument fields.
     *
     * @param  array<string,mixed>  $entity
     * @return array<string,mixed>
     */
    protected function mapEntityToFields(array $entity): array
    {
        $updates = [];

        // Labels / Descriptions
        $labelTr = Arr::get($entity, 'labels.tr.value');
        $descTr = Arr::get($entity, 'descriptions.tr.value');
        $descEn = Arr::get($entity, 'descriptions.en.value');
        if ($labelTr) {
            $updates['name_tr'] = $updates['name_tr'] ?? $labelTr;
        }
        if ($descTr) {
            $updates['description_tr'] = $descTr;
        }
        if ($descEn) {
            $updates['description_en'] = $descEn;
        }

        // Aliases (TR) - store as comma-separated string
        $aliasesTr = [];
        foreach ((array) Arr::get($entity, 'aliases.tr', []) as $alias) {
            if (isset($alias['value'])) {
                $aliasesTr[] = (string) $alias['value'];
            }
        }
        if (! empty($aliasesTr)) {
            $updates['aka'] = implode(', ', $aliasesTr);
        }

        // Claims helpers
        $firstItemId = function (string $prop) use ($entity): ?string {
            $claims = $entity['claims'][$prop] ?? [];
            foreach ($claims as $claim) {
                $dv = $claim['mainsnak']['datavalue']['value'] ?? null;
                if (is_array($dv) && isset($dv['id'])) {
                    return (string) $dv['id'];
                }
            }

            return null;
        };

        $firstStringValue = function (string $prop) use ($entity): ?string {
            $claims = $entity['claims'][$prop] ?? [];
            foreach ($claims as $claim) {
                $dv = $claim['mainsnak']['datavalue']['value'] ?? null;
                if (is_string($dv)) {
                    return $dv;
                }
            }

            return null;
        };

        // P11729: Kültür Envanteri ID
        $p11729 = $firstStringValue('P11729');
        if ($p11729) {
            $updates['kulturenvanteri_id'] = $p11729;
        }

        // P373: Commons category
        $p373 = $firstStringValue('P373');
        if ($p373) {
            $updates['commons_category'] = $p373;
        }

        // P131: located in the administrative territorial entity
        $p131 = $firstItemId('P131');
        if ($p131) {
            $updates['district'] = $p131; // store Q-code; views resolve label at runtime
            $updates['properties']['admin_label_tr'] = WikidataSparqlService::getLabelForQCode($p131);
        }

        // P706: located in/on physical feature
        $p706 = $firstItemId('P706');
        if ($p706) {
            $updates['properties']['physical_feature'] = $p706;
            $updates['properties']['physical_feature_label_tr'] = WikidataSparqlService::getLabelForQCode($p706);
        }

        // P31: instance of
        $p31 = $firstItemId('P31');
        if ($p31) {
            $updates['properties']['instance_of'] = $p31;
            $updates['properties']['instance_of_label_tr'] = WikidataSparqlService::getLabelForQCode($p31);
        }

        return $updates;
    }
}