<?php

namespace App\Console\Commands;

use App\Jobs\SyncMonumentDescriptions;
use App\Jobs\SyncMonumentsUnifiedJob;
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

        try {
            $startTime = microtime(true);

            // Dispatch as a queued job so it can be scheduled or run async as needed
            SyncMonumentsUnifiedJob::dispatch($batchSize, $maxBatches);
            $syncedCount = 0;

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info('✅ Unified sync job dispatched successfully!');
            $this->info("⏱️ Dispatch time {$duration} seconds (processing continues in queue)");

            $this->info('⚡ Average will be logged by the job after completion.');

            // Summary of what was synced in this unified approach
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

            Log::info('Unified monuments sync command dispatched job', [
                'batch_size' => $batchSize,
                'max_batches' => $maxBatches,
            ]);

            // Queue a follow-up job to backfill descriptions and related details
            // for monuments that still have missing fields (does not mix languages)
            // Follow-ups happen inside the job

        } catch (\Exception $e) {
            $this->error('❌ Unified sync failed: '.$e->getMessage());

            Log::error('Unified monuments sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'batch_size' => $batchSize,
                'max_batches' => $maxBatches,
            ]);

            return 1;
        }

        $this->newLine();
        $this->info('🎉 All done! The unified sync approach has replaced the need for multiple scattered commands.');

        return 0;
    }
}
