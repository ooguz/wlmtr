<?php

namespace App\Console\Commands;

use App\Jobs\SyncMonumentDescriptions;
use Illuminate\Support\Facades\Cache;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMonumentsUnified extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monuments:sync-unified 
                           {--batch-size=500 : Number of monuments to fetch per batch (default: 500)}
                           {--max-batches=60 : Maximum number of batches to process (default: 60)}
                           {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Unified monument synchronization using optimized SPARQL query that fetches all data in one go';

    /**
     * Execute the console command.
     */
    public function handle(WikidataSparqlService $sparqlService): int
    {
       /* if (! $this->option('force')) {
            if (! $this->confirm('This will sync all monument data using the unified approach. Continue?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        } */

        $this->info('🚀 Starting unified monuments sync from Wikidata...');
        $this->info('📊 Using optimized SPARQL query that fetches comprehensive data');

        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');

        if ($maxBatches) {
            $this->info("📋 Will process maximum {$maxBatches} batches of {$batchSize} monuments each");
        } else {
            $this->info("📋 Will process all available monuments in batches of {$batchSize}");
        }

        // prevent overlapping with the scheduled job or another manual run
        $lock = Cache::lock('jobs:sync-monuments-unified', 1800);
        if (! $lock->get()) {
            $this->warn('Another unified sync is already running. Exiting.');

            return 0;
        }

        try {
            $startTime = microtime(true);

            $syncedCount = $sparqlService->syncMonumentsToDatabase($batchSize, $maxBatches, function (string $event, array $payload = []) {
                switch ($event) {
                    case 'start_batch':
                        $this->newLine();
                        $this->info("🔄 Batch {$payload['batch']} starting (offset {$payload['offset']}, limit {$payload['limit']})");
                        break;
                    case 'end_batch':
                        $status = $payload['http_status'] ?? 'n/a';
                        $this->line("   ✅ Batch {$payload['batch']} → synced: {$payload['synced']}, new: {$payload['new']}, updated: {$payload['updated']}, errors: {$payload['errors']} (total: {$payload['total']}) | status: {$status}");

                        $examples = $payload['examples'] ?? [];
                        if (! empty($examples)) {
                            $this->line('     e.g.:');
                            foreach ($examples as $ex) {
                                $name = $ex['name_tr'] ?? '-';
                                $qid = $ex['wikidata_id'] ?? '-';
                                $ke = $ex['kulturenvanteri_id'] ?? '-';
                                $this->line("       - {$name} ({$qid}) KE: {$ke}");
                            }
                        }
                        break;
                    case 'complete':
                        $this->newLine();
                        $this->info('🎯 Unified monuments sync completed');
                        $this->line("   Total synced: {$payload['total_synced']}, new: {$payload['total_new']}, updated: {$payload['total_updated']}, errors: {$payload['total_errors']}");
                        $this->line("   Batches processed: {$payload['batches_processed']} (batch size: {$payload['batch_size']})");
                        break;
                }
            });

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("✅ Unified sync finished in {$duration} seconds");

            // Summary of what was synchronized
            $this->newLine();
            $this->info('📋 Data synchronized per monument:');
            $this->line('  • Name (Turkish)');
            $this->line('  • Description (Turkish)');
            $this->line('  • Aliases/Alternative names');
            $this->line('  • Kültür Envanteri ID');
            $this->line('  • Commons category');
            $this->line('  • Coordinates');
            $this->line('  • Instance of (type)');
            $this->line('  • Wikipedia links (Turkish and English)');
            $this->line('  • Images (if available)');
            $this->line('  • Location hierarchy (via P131 chain)');

            Log::info('Unified monuments sync command completed', [
                'batch_size' => $batchSize,
                'max_batches' => $maxBatches,
                'synced' => $syncedCount,
                'duration' => $duration,
            ]);

            // Trigger backfill for descriptions if needed (synchronously enqueue)
            SyncMonumentDescriptions::dispatch();

        } catch (\Exception $e) {
            $this->error('❌ Unified sync failed: '.$e->getMessage());

            Log::error('Unified monuments sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'batch_size' => $batchSize,
                'max_batches' => $maxBatches,
            ]);

            return 1;
        } finally {
            optional($lock)->release();
        }

        $this->newLine();
        $this->info('🎉 All done! The unified sync approach has replaced the need for multiple scattered commands.');

        return 0;
    }
}
