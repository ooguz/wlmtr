<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use App\Services\WikimediaCommonsService;

class FixPhotoUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:fix-urls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix photo URLs by re-encoding them correctly for Wikimedia Commons';

    /**
     * Execute the console command.
     */
    public function handle(WikimediaCommonsService $commonsService)
    {
        $photos = Photo::all();
        $bar = $this->output->createProgressBar($photos->count());
        $bar->start();
        
        foreach ($photos as $photo) {
            // Fetch info from Commons
            $info = $commonsService->fetchImageInfoForFiles(['File:' . $photo->commons_filename]);
            if (!empty($info) && isset($info[0])) {
                $data = $info[0];
                $photo->original_url = $data['original_url'] ?? $photo->original_url;
                $photo->thumbnail_url = $data['thumbnail_url'] ?? $photo->thumbnail_url;
                $photo->photographer = $data['photographer'] ?? $photo->photographer;
                $photo->license = $data['license'] ?? $photo->license;
                $photo->license_shortname = $data['license_shortname'] ?? $photo->license_shortname;
                $photo->commons_url = $data['commons_url'] ?? $photo->commons_url;
                $photo->save();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info("\nAll photo URLs, author, and license info updated.");
    }
    
    /**
     * Extract filename from Wikimedia Commons URL.
     */
    private function extractFilenameFromUrl(string $url): ?string
    {
        // Extract from upload.wikimedia.org URLs
        if (preg_match('/upload\.wikimedia\.org\/wikipedia\/commons\/(.+)$/', $url, $matches)) {
            return urldecode($matches[1]);
        }
        
        // Extract from thumb.php URLs
        if (preg_match('/f=([^&]+)/', $url, $matches)) {
            return urldecode($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Build correct original URL.
     */
    private function buildCorrectOriginalUrl(string $filename): string
    {
        $encodedFilename = rawurlencode($filename);
        return "https://upload.wikimedia.org/wikipedia/commons/{$encodedFilename}";
    }
    
    /**
     * Build correct thumbnail URL.
     */
    private function buildCorrectThumbnailUrl(string $filename): string
    {
        $encodedFilename = rawurlencode($filename);
        return "https://commons.wikimedia.org/w/thumb.php?f={$encodedFilename}&width=300";
    }
} 