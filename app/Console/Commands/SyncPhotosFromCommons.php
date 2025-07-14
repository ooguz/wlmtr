<?php

namespace App\Console\Commands;

use App\Services\WikimediaCommonsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPhotosFromCommons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:sync-from-commons';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync photos from Wikimedia Commons for all monuments';

    /**
     * Execute the console command.
     */
    public function handle(WikimediaCommonsService $commonsService)
    {
        $this->info('Starting photos sync from Wikimedia Commons...');
        
        try {
            $startTime = microtime(true);
            
            $totalPhotos = $commonsService->syncPhotosForAllMonuments();
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->info("Photos sync completed successfully!");
            $this->info("Synced {$totalPhotos} photos in {$duration} seconds");
            
            Log::info('Photos sync command completed', [
                'total_photos' => $totalPhotos,
                'duration_seconds' => $duration,
            ]);
            
        } catch (\Exception $e) {
            $this->error('Photos sync failed: ' . $e->getMessage());
            
            Log::error('Photos sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
        
        return 0;
    }
} 