<?php

namespace App\Jobs;

use App\Jobs\SyncMonumentDescriptions;
use App\Services\WikidataSparqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMonumentsUnifiedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $batchSize = 500, public int $maxBatches = 60)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(WikidataSparqlService $sparqlService): void
    {
        $startTime = microtime(true);

        $syncedCount = $sparqlService->syncMonumentsToDatabase($this->batchSize, $this->maxBatches);

        $duration = round(microtime(true) - $startTime, 2);
        $avgTime = $syncedCount > 0 ? round($duration / $syncedCount, 3) : 0;

        Log::info('SyncMonumentsUnifiedJob completed', [
            'synced_count' => $syncedCount,
            'duration_seconds' => $duration,
            'batch_size' => $this->batchSize,
            'max_batches' => $this->maxBatches,
            'avg_time_per_monument' => $avgTime,
        ]);

        // Follow-up: backfill descriptions for any remaining gaps
        SyncMonumentDescriptions::dispatch();
    }
}


