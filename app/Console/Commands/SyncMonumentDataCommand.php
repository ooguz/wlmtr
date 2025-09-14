<?php

namespace App\Console\Commands;

use App\Jobs\SyncAllMonumentData;
use App\Jobs\SyncMonumentDescriptions;
use App\Jobs\SyncMonumentLocations;
use Illuminate\Console\Command;

class SyncMonumentDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:sync-data 
                            {--type=all : Type of sync to run (all, locations, descriptions)}
                            {--dispatch : Dispatch job to queue instead of running immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync monument data from Wikidata (locations, descriptions)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $dispatch = $this->option('dispatch');

        $this->info("Starting monument data synchronization (type: {$type})");

        switch ($type) {
            case 'locations':
                $this->syncLocations($dispatch);
                break;
            case 'descriptions':
                $this->syncDescriptions($dispatch);
                break;
            case 'all':
            default:
                $this->syncAll($dispatch);
                break;
        }

        $this->info('Monument data synchronization completed');
        return Command::SUCCESS;
    }

    /**
     * Sync monument locations.
     */
    private function syncLocations(bool $dispatch): void
    {
        if ($dispatch) {
            SyncMonumentLocations::dispatch();
            $this->info('Location sync job dispatched to queue');
        } else {
            $this->info('Running location sync immediately...');
            $job = new SyncMonumentLocations();
            $job->handle();
        }
    }

    /**
     * Sync monument descriptions.
     */
    private function syncDescriptions(bool $dispatch): void
    {
        if ($dispatch) {
            SyncMonumentDescriptions::dispatch();
            $this->info('Description sync job dispatched to queue');
        } else {
            $this->info('Running description sync immediately...');
            $job = new SyncMonumentDescriptions();
            $job->handle();
        }
    }

    /**
     * Sync all monument data.
     */
    private function syncAll(bool $dispatch): void
    {
        if ($dispatch) {
            SyncAllMonumentData::dispatch();
            $this->info('Comprehensive sync job dispatched to queue');
        } else {
            $this->info('Running comprehensive sync immediately...');
            $job = new SyncAllMonumentData();
            $job->handle();
        }
    }
}