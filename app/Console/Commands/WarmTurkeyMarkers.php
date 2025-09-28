<?php

namespace App\Console\Commands;

use App\Jobs\WarmTurkeyMarkersJob;
use Illuminate\Console\Command;

class WarmTurkeyMarkers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warm-turkey-markers';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch a job to warm the cache for Turkey-wide markers.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        WarmTurkeyMarkersJob::dispatch();
        $this->info('WarmTurkeyMarkersJob dispatched.');
        return self::SUCCESS;
    }
}
