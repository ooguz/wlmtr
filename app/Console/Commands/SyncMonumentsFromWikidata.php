<?php

namespace App\Console\Commands;

use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMonumentsFromWikidata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:sync-from-wikidata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync monuments data from Wikidata using SPARQL query';

    /**
     * Execute the console command.
     */
    public function handle(WikidataSparqlService $sparqlService)
    {
        $this->info('Starting monuments sync from Wikidata...');
        
        try {
            $startTime = microtime(true);
            
            $syncedCount = $sparqlService->syncMonumentsToDatabase();
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->info("Sync completed successfully!");
            $this->info("Synced {$syncedCount} monuments in {$duration} seconds");
            
            Log::info('Monuments sync command completed', [
                'synced_count' => $syncedCount,
                'duration_seconds' => $duration,
            ]);
            
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            
            Log::error('Monuments sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
        
        return 0;
    }
}
