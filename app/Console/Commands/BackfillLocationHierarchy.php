<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;

class BackfillLocationHierarchy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:backfill-location {--q=} {--limit=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build and store formatted TR location hierarchy for monuments using Wikidata P131 chain';

    /**
     * Execute the console command.
     */
    public function handle(WikidataSparqlService $service): int
    {
        $q = (string) $this->option('q');
        $limit = (int) $this->option('limit');

        $query = Monument::query();
        if ($q !== '') {
            $query->where('wikidata_id', $q);
        } else {
            $query->whereNull('location_hierarchy_tr');
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $monuments = $query->get();
        $this->info('Processing '.$monuments->count().' monument(s)');

        $updated = 0;
        foreach ($monuments as $monument) {
            if (! $monument->wikidata_id) {
                continue;
            }

            $labels = $service->fetchP131HierarchyLabels($monument->wikidata_id);
            if (empty($labels)) {
                $this->line('- '.$monument->wikidata_id.' no hierarchy');

                continue;
            }

            $monument->location_hierarchy_tr = implode(', ', $labels);
            $monument->save();
            $updated++;
            $this->line('✓ '.$monument->wikidata_id.' -> '.$monument->location_hierarchy_tr);
        }

        $this->info('Done. Updated '.$updated.' monument(s).');

        return 0;
    }
}
