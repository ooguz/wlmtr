<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicatePhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:cleanup-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate photos in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to clean up duplicate photos...');
        
        // Find duplicates based on normalized filename (replace spaces with underscores)
        $duplicates = DB::select("
            SELECT 
                REPLACE(commons_filename, ' ', '_') as normalized_filename,
                COUNT(*) as count
            FROM photos 
            GROUP BY REPLACE(commons_filename, ' ', '_')
            HAVING COUNT(*) > 1
        ");
        
        $removedCount = 0;
        
        foreach ($duplicates as $duplicate) {
            $this->info("Found {$duplicate->count} duplicates for: {$duplicate->normalized_filename}");
            
            // Get all photos with this normalized filename
            $photos = Photo::whereRaw("REPLACE(commons_filename, ' ', '_') = ?", [$duplicate->normalized_filename])
                          ->orderBy('id')
                          ->get();
            
            // Keep the first one, delete the rest
            $firstPhoto = $photos->first();
            $photosToDelete = $photos->slice(1);
            
            foreach ($photosToDelete as $photo) {
                $this->line("  Deleting photo ID {$photo->id} with filename: {$photo->commons_filename}");
                $photo->delete();
                $removedCount++;
            }
        }
        
        $this->info("Cleanup completed. Removed {$removedCount} duplicate photos.");
        
        // Update remaining photos to use underscore format
        $this->info('Updating remaining photos to use underscore format...');
        $updatedCount = 0;
        
        $photos = Photo::where('commons_filename', 'like', '% %')->get();
        foreach ($photos as $photo) {
            $oldFilename = $photo->commons_filename;
            $newFilename = str_replace(' ', '_', $oldFilename);
            
            $photo->update(['commons_filename' => $newFilename]);
            $this->line("  Updated: {$oldFilename} -> {$newFilename}");
            $updatedCount++;
        }
        
        $this->info("Updated {$updatedCount} photos to use underscore format.");
    }
} 