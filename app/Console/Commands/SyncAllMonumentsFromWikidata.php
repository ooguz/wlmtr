<?php

namespace App\Console\Commands;

use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncAllMonumentsFromWikidata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:sync-all-from-wikidata 
                            {--batch-size=1000 : Number of monuments to fetch per batch}
                            {--max-batches=20 : Maximum number of batches to process}
                            {--lock-ttl=3600 : Lock TTL in seconds (prevents concurrent runs)}
                            {--force : Force release an existing stale lock before starting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync ALL monuments from Wikidata using batched SPARQL queries';

    /**
     * Execute the console command.
     */
    public function handle(WikidataSparqlService $sparqlService)
    {
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $lockTtl = (int) $this->option('lock-ttl');
        $force = (bool) $this->option('force');
        
        $this->info("Starting FULL monuments sync from Wikidata...");
        $this->info("Batch size: {$batchSize}, Max batches: {$maxBatches}");
        
        // Prevent concurrent runs, allow force unlock
        $lockName = 'monuments_sync_lock';
        if ($force) {
            try {
                Cache::lock($lockName, 1)->forceRelease();
                $this->line('Released existing lock (force).');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $lock = Cache::lock($lockName, max(60, $lockTtl));
        try {
            // Wait up to 10s to acquire lock to avoid races
            $acquired = $lock->block(10);
            if (! $acquired) {
                $this->warn('Another monuments sync is already running. Exiting.');
                return 0;
            }
        } catch (\Throwable $e) {
            $this->warn('Could not acquire sync lock. Exiting.');
            return 0;
        }

        try {
            $startTime = microtime(true);
            
            $totalSynced = $sparqlService->syncMonumentsToDatabase($batchSize, $maxBatches);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->info("Full sync completed successfully!");
            $this->info("Total synced: {$totalSynced} monuments in {$duration} seconds");
            
            Log::info('Full monuments sync command completed', [
                'total_synced' => $totalSynced,
                'duration_seconds' => $duration,
                'batch_size' => $batchSize,
            ]);
            
        } catch (\Exception $e) {
            $this->error('Full sync failed: ' . $e->getMessage());
            
            Log::error('Full monuments sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
        finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 0;
    }
}
