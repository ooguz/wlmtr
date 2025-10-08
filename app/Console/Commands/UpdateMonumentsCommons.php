<?php

namespace App\Console\Commands;

use App\Jobs\UpdateMonumentsCommonsJob;
use App\Models\Monument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMonumentsCommons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:update-commons 
                            {--batch-size=100 : Number of monuments to process per batch}
                            {--queue : Run the job in the queue}
                            {--sync : Run synchronously without queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update commons category (P373) and image (P18) for all monuments from Wikidata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $useQueue = $this->option('queue');
        $sync = $this->option('sync');

        if ($sync && $useQueue) {
            $this->error('Cannot use both --sync and --queue options together');

            return 1;
        }

        $this->info('Starting commons category and image update for monuments...');

        try {
            // Count monuments that need updating
            $totalMonuments = Monument::whereNotNull('wikidata_id')
                ->where(function ($query) {
                    $query->whereNull('commons_category')
                        ->orWhereNull('properties->image');
                })
                ->count();

            if ($totalMonuments === 0) {
                $this->info('No monuments found that need commons data updates.');

                return 0;
            }

            $this->info("Found {$totalMonuments} monuments that need commons data updates.");

            $totalBatches = ceil($totalMonuments / $batchSize);
            $this->info("Will process in {$totalBatches} batches of {$batchSize} monuments each.");

            if ($sync) {
                $this->info('Running synchronously...');
                $this->runSync($batchSize, $totalBatches);
            } else {
                $this->info($useQueue ? 'Dispatching jobs to queue...' : 'Running in background...');
                $this->runAsync($batchSize, $totalBatches, $useQueue);
            }

            $this->info('Commons update completed successfully!');

            Log::info('Monuments commons update command completed', [
                'total_monuments' => $totalMonuments,
                'batch_size' => $batchSize,
                'total_batches' => $totalBatches,
                'sync' => $sync,
                'queued' => $useQueue,
            ]);

        } catch (\Exception $e) {
            $this->error('Commons update failed: '.$e->getMessage());

            Log::error('Monuments commons update command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }

    /**
     * Run the update synchronously.
     */
    private function runSync(int $batchSize, int $totalBatches): void
    {
        $progressBar = $this->output->createProgressBar($totalBatches);
        $progressBar->start();

        for ($i = 0; $i < $totalBatches; $i++) {
            $offset = $i * $batchSize;

            $job = new UpdateMonumentsCommonsJob($offset, $batchSize);
            $job->handle(app(\App\Services\WikidataSparqlService::class));

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Run the update asynchronously (with or without queue).
     */
    private function runAsync(int $batchSize, int $totalBatches, bool $useQueue): void
    {
        $progressBar = $this->output->createProgressBar($totalBatches);
        $progressBar->start();

        for ($i = 0; $i < $totalBatches; $i++) {
            $offset = $i * $batchSize;

            if ($useQueue) {
                UpdateMonumentsCommonsJob::dispatch($offset, $batchSize);
            } else {
                UpdateMonumentsCommonsJob::dispatchSync($offset, $batchSize);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($useQueue) {
            $this->info('Jobs have been dispatched to the queue. Monitor the queue worker to see progress.');
        } else {
            $this->info('All jobs have been dispatched and will run in the background.');
        }
    }
}
