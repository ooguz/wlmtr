<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\WikidataSparqlService;
use Illuminate\Console\Command;

class SyncCategoriesFromWikidata extends Command
{
    protected $signature = 'categories:sync-wikidata {--limit=100 : Maximum number of categories to sync}';
    protected $description = 'Sync monument categories from Wikidata';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info('Starting category synchronization from Wikidata...');
        
        $sparqlService = new WikidataSparqlService();
        $categories = $sparqlService->fetchMonumentCategories();
        
        if (empty($categories)) {
            $this->warn('No categories found from Wikidata');
            return Command::SUCCESS;
        }
        
        $this->info("Found {count($categories)} categories from Wikidata");
        
        $syncedCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        
        foreach (array_slice($categories, 0, $limit) as $categoryData) {
            try {
                $category = Category::updateOrCreate(
                    ['wikidata_id' => $categoryData['wikidata_id']],
                    [
                        'name_tr' => $categoryData['name_tr'],
                        'name_en' => $categoryData['name_en'],
                        'description_tr' => $categoryData['description_tr'],
                        'description_en' => $categoryData['description_en'],
                        'is_active' => true,
                    ]
                );
                
                if ($category->wasRecentlyCreated) {
                    $createdCount++;
                    $this->line("Created: {$category->primary_name}");
                } else {
                    $updatedCount++;
                    $this->line("Updated: {$category->primary_name}");
                }
                
                $syncedCount++;
            } catch (\Exception $e) {
                $this->error("Failed to sync category {$categoryData['wikidata_id']}: {$e->getMessage()}");
            }
        }
        
        $this->info("Category sync completed:");
        $this->info("- Total synced: {$syncedCount}");
        $this->info("- Created: {$createdCount}");
        $this->info("- Updated: {$updatedCount}");
        
        return Command::SUCCESS;
    }
}