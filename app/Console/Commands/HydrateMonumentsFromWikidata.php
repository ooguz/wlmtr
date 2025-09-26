<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class HydrateMonumentsFromWikidata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:hydrate-missing {--q=} {--limit=200} {--update-existing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hydrate monument fields (descriptions, aliases, commons category, kulturenvanteri ID) from Wikidata entity JSON';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $qcode = (string) $this->option('q');
        $limit = (int) $this->option('limit');
        $updateExisting = $this->option('update-existing');

        $query = Monument::query();
        if ($qcode !== '') {
            $query->where('wikidata_id', $qcode);
        } else {
            if ($updateExisting) {
                // Update all monuments
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
        }

        $monuments = $query->limit($limit)->get();
        $this->info('Hydrating '.$monuments->count().' monument(s)');

        $updated = 0;
        foreach ($monuments as $monument) {
            try {
                if (! $monument->wikidata_id) {
                    continue;
                }

                $entity = WikidataSparqlService::getEntityData($monument->wikidata_id);
                if (! is_array($entity) || empty($entity)) {
                    $this->warn('No entity for '.$monument->wikidata_id);

                    continue;
                }

                $updates = $this->mapEntityToFields($entity);
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
                $this->line('✓ '.$monument->wikidata_id.' updated');
            } catch (\Throwable $e) {
                Log::error('Hydration failed', [
                    'wikidata_id' => $monument->wikidata_id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn('! Failed '.$monument->wikidata_id.': '.$e->getMessage());
            }
        }

        $this->info('Done. Updated '.$updated.' monument(s).');

        return 0;
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
