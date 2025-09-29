<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;

class TargetedSyncMissingKulturId extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monuments:sync-missing-ke
                            {--chunk=100 : Number of QIDs to fetch per request}
                            {--limit=0 : Limit number of records processed (0=all)}';

    /**
     * The console command description.
     */
    protected $description = 'Find monuments missing kulturenvanteri_id and sync only those from Wikidata by QID.';

    public function handle(WikidataSparqlService $sparql): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));

        $this->info('🔎 Finding monuments missing kulturenvanteri_id...');

        $query = Monument::query()
            ->select(['wikidata_id'])
            ->whereNull('kulturenvanteri_id')
            ->whereNotNull('wikidata_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $qids = $query->pluck('wikidata_id')->filter()->unique()->values()->all();

        $total = count($qids);
        if ($total === 0) {
            $this->info('✅ Nothing to sync. All monuments have kulturenvanteri_id.');

            return 0;
        }

        $this->info("Found {$total} monuments to sync. Chunk size: {$chunk}");

        $synced = 0;
        $errors = 0;
        $updated = 0;
        $newlyCompleted = 0;

        foreach (array_chunk($qids, $chunk) as $index => $qidChunk) {
            $batchNo = $index + 1;
            $this->line("🔄 Fetching batch {$batchNo} (".count($qidChunk)." QIDs)...");

            $monuments = $sparql->fetchMonumentsByQids($qidChunk);

            if (empty($monuments)) {
                $this->warn('  ⚠️ No data returned for this batch.');
                continue;
            }

            $batchSynced = 0;
            foreach ($monuments as $data) {
                try {
                    $nonNull = array_filter($data, static function ($v) {
                        return $v !== null;
                    });
                    $before = Monument::where('wikidata_id', $data['wikidata_id'])->first();

                    $model = Monument::updateOrCreate(
                        ['wikidata_id' => $data['wikidata_id']],
                        array_merge($nonNull, ['last_synced_at' => now()])
                    );

                    $batchSynced++;
                    $synced++;
                    if ($before && empty($before->kulturenvanteri_id) && ! empty($model->kulturenvanteri_id)) {
                        $newlyCompleted++;
                    }
                    if ($before) {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error('  ❌ Failed to update '.$data['wikidata_id'].': '.$e->getMessage());
                }
            }

            $this->line("   ✅ Batch {$batchNo} → synced: {$batchSynced}, total so far: {$synced}, errors: {$errors}");
        }

        $this->newLine();
        $this->info('🎯 Targeted sync completed');
        $this->line("   Total processed: {$synced}, errors: {$errors}");
        $this->line("   Newly filled kulturenvanteri_id: {$newlyCompleted}");

        return $errors > 0 ? 1 : 0;
    }
}


