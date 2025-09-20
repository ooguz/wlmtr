<?php

namespace App\Console\Commands;

use App\Jobs\SyncMonumentPhotos;
use Illuminate\Console\Command;

class SyncMonumentPhotosCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monuments:sync-photos 
                            {--limit= : Limit the number of monuments to process}
                            {--force : Force update even if photos already exist}
                            {--queue : Run the sync job in the background queue}';

    /**
     * The console command description.
     */
    protected $description = 'Sync monument photos from Wikidata to the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $forceUpdate = $this->option('force');
        $useQueue = $this->option('queue');

        $this->info('Starting monument photos sync...');
        $this->info('Options:');
        $this->info("  - Limit: " . ($limit ?: 'No limit'));
        $this->info("  - Force update: " . ($forceUpdate ? 'Yes' : 'No'));
        $this->info("  - Use queue: " . ($useQueue ? 'Yes' : 'No'));

        if ($useQueue) {
            // Dispatch the job to the queue
            SyncMonumentPhotos::dispatch($limit, $forceUpdate);
            $this->info('Photo sync job dispatched to queue.');
            $this->info('Check the logs for progress updates.');
        } else {
            // Run synchronously
            $job = new SyncMonumentPhotos($limit, $forceUpdate);
            $job->handle();
            $this->info('Photo sync completed.');
        }

        return Command::SUCCESS;
    }
}