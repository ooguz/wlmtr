<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePhotoMetadata;
use Illuminate\Console\Command;

class UpdatePhotoMetadataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'photos:update-metadata 
                            {--limit= : Limit the number of photos to process}
                            {--force : Force update even if metadata already exists}
                            {--queue : Run the update job in the background queue}';

    /**
     * The console command description.
     */
    protected $description = 'Update photo metadata (photographer, license) from Wikimedia Commons';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $forceUpdate = $this->option('force');
        $useQueue = $this->option('queue');

        $this->info('Starting photo metadata update...');
        $this->info('Options:');
        $this->info("  - Limit: " . ($limit ?: 'No limit'));
        $this->info("  - Force update: " . ($forceUpdate ? 'Yes' : 'No'));
        $this->info("  - Use queue: " . ($useQueue ? 'Yes' : 'No'));

        if ($useQueue) {
            // Dispatch the job to the queue
            UpdatePhotoMetadata::dispatch($limit, $forceUpdate);
            $this->info('Photo metadata update job dispatched to queue.');
            $this->info('Check the logs for progress updates.');
        } else {
            // Run synchronously
            $job = new UpdatePhotoMetadata($limit, $forceUpdate);
            $job->handle();
            $this->info('Photo metadata update completed.');
        }

        return Command::SUCCESS;
    }
}