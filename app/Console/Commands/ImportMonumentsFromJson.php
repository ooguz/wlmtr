<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Monument;
use App\Models\Photo;
use Illuminate\Console\Command;

class ImportMonumentsFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:monuments 
                            {--monuments=monuments_export.json : Path to monuments JSON file}
                            {--photos=photos_export.json : Path to photos JSON file}
                            {--categories=categories_export.json : Path to categories JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import monuments, photos and categories from JSON export files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monumentsFile = $this->option('monuments');
        $photosFile = $this->option('photos');
        $categoriesFile = $this->option('categories');

        $this->info('Starting import from JSON files...');

        // Import categories first
        if (file_exists($categoriesFile)) {
            $this->importCategories($categoriesFile);
        }

        // Import monuments
        if (file_exists($monumentsFile)) {
            $this->importMonuments($monumentsFile);
        }

        // Import photos
        if (file_exists($photosFile)) {
            $this->importPhotos($photosFile);
        }

        $this->info('Import completed successfully!');
        return 0;
    }

    private function importCategories(string $file): void
    {
        $this->info("Importing categories from {$file}...");
        
        $data = json_decode(file_get_contents($file), true);
        $imported = 0;

        foreach ($data as $categoryData) {
            Category::updateOrCreate(
                ['id' => $categoryData['id']],
                $categoryData
            );
            $imported++;
        }

        $this->info("Imported {$imported} categories");
    }

    private function importMonuments(string $file): void
    {
        $this->info("Importing monuments from {$file}...");
        
        $data = json_decode(file_get_contents($file), true);
        $imported = 0;

        foreach ($data as $monumentData) {
            // Remove timestamps to avoid conflicts
            unset($monumentData['created_at'], $monumentData['updated_at']);
            
            Monument::updateOrCreate(
                ['wikidata_id' => $monumentData['wikidata_id']],
                $monumentData
            );
            $imported++;
        }

        $this->info("Imported {$imported} monuments");
    }

    private function importPhotos(string $file): void
    {
        $this->info("Importing photos from {$file}...");
        
        $data = json_decode(file_get_contents($file), true);
        $imported = 0;

        foreach ($data as $photoData) {
            // Remove timestamps to avoid conflicts
            unset($photoData['created_at'], $photoData['updated_at']);
            
            Photo::updateOrCreate(
                ['id' => $photoData['id']],
                $photoData
            );
            $imported++;
        }

        $this->info("Imported {$imported} photos");
    }
}
