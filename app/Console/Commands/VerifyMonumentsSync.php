<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;

class VerifyMonumentsSync extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monuments:verify-sync
                            {--show-missing : Print missing Wikidata QIDs}
                            {--page-size=2000 : Page size for SPARQL QID fetch}';

    /**
     * The console command description.
     */
    protected $description = 'Verify that all monuments from SPARQL exist in the database; optionally list missing QIDs.';

    public function handle(WikidataSparqlService $sparql): int
    {
        $this->info('🔎 Verifying monuments sync against SPARQL...');

        $expected = $sparql->fetchMonumentsCount();
        $inDb = (int) MonumentsCount::countDistinct();

        $this->line("SPARQL expected (distinct): {$expected}");
        $this->line("Database distinct wikidata_id: {$inDb}");

        if ($expected <= 0) {
            $this->warn('SPARQL returned 0 or error; cannot verify.');
            return 1;
        }

        if ($this->option('show-missing')) {
            $pageSize = (int) $this->option('page-size');
            $missing = $this->computeMissingQids($sparql, $pageSize);
            $missingCount = count($missing);
            $this->line("Missing in DB: {$missingCount}");
            if ($missingCount > 0) {
                foreach ($missing as $qid) {
                    $this->line(" - {$qid}");
                }
            }
        }

        if ($inDb >= $expected) {
            $this->info('✅ Database has all expected monuments (or more).');
            return 0;
        }

        $this->warn('⚠️ Database is missing some monuments.');
        return 1;
    }

    /**
     * Compute missing QIDs by paging through SPARQL QID list and comparing against DB.
     *
     * @return array<int,string>
     */
    private function computeMissingQids(WikidataSparqlService $sparql, int $pageSize): array
    {
        $dbQids = MonumentsCount::pluckDistinct();
        $dbSet = array_fill_keys($dbQids, true);

        $missing = [];
        $offset = 0;
        while (true) {
            $qids = $sparql->fetchMonumentQids($offset, $pageSize);
            if (empty($qids)) {
                break;
            }
            foreach ($qids as $qid) {
                if (! isset($dbSet[$qid])) {
                    $missing[] = $qid;
                }
            }
            $offset += $pageSize;
            if (count($qids) < $pageSize) {
                break;
            }
        }

        return $missing;
    }
}

/**
 * Tiny helper for DB counts to keep Eloquent logic tidy and testable.
 */
final class MonumentsCount
{
    public static function countDistinct(): int
    {
        return (int) Monument::query()->distinct('wikidata_id')->count('wikidata_id');
    }

    /**
     * @return array<int,string>
     */
    public static function pluckDistinct(): array
    {
        return Monument::query()->whereNotNull('wikidata_id')->distinct('wikidata_id')->pluck('wikidata_id')->all();
    }
}

