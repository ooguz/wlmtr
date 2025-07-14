<?php

namespace App\Console\Commands;

use App\Models\Monument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLocationNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monuments:fix-location-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix location names that are still Wikidata URIs by converting them to Wikidata IDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting location names fix...');

        $updatedCount = 0;

        // Fix province names
        $updatedCount += $this->fixLocationField('province');
        
        // Fix city names
        $updatedCount += $this->fixLocationField('city');
        
        // Fix district names
        $updatedCount += $this->fixLocationField('district');

        $this->info("Location names fix completed! Updated {$updatedCount} records.");
    }

    private function fixLocationField(string $field): int
    {
        $updatedCount = 0;
        
        $monuments = Monument::where($field, 'like', 'http://www.wikidata.org/entity/%')
            ->orWhere($field, 'like', 'https://www.wikidata.org/entity/%')
            ->get();

        foreach ($monuments as $monument) {
            $oldValue = $monument->$field;
            $newValue = $this->extractWikidataId($oldValue);
            
            if ($newValue) {
                $monument->$field = $newValue;
                $monument->save();
                $updatedCount++;
                
                $this->line("Updated {$field} for monument ID {$monument->id}: {$oldValue} -> {$newValue}");
            }
        }

        return $updatedCount;
    }

    private function extractWikidataId(?string $uri): ?string
    {
        if (!$uri) {
            return null;
        }

        if (preg_match('/Q\d+$/', $uri, $matches)) {
            return $matches[0];
        }

        return null;
    }
} 