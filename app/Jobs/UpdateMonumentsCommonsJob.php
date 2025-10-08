<?php

namespace App\Jobs;

use App\Models\Monument;
use App\Services\WikidataSparqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateMonumentsCommonsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $offset = 0,
        public int $limit = 100
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WikidataSparqlService $sparqlService): void
    {
        Log::info('Starting commons category and image update job', [
            'offset' => $this->offset,
            'limit' => $this->limit,
        ]);

        try {
            // Get monuments that need updating (have wikidata_id but missing commons data)
            $monuments = Monument::whereNotNull('wikidata_id')
                ->where(function ($query) {
                    $query->whereNull('commons_category')
                        ->orWhereNull('properties->image');
                })
                ->offset($this->offset)
                ->limit($this->limit)
                ->get();

            if ($monuments->isEmpty()) {
                Log::info('No monuments found that need commons data updates');

                return;
            }

            $updatedCount = 0;
            $errorCount = 0;

            foreach ($monuments as $monument) {
                try {
                    $this->updateMonumentCommonsData($monument, $sparqlService);
                    $updatedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to update commons data for monument', [
                        'monument_id' => $monument->id,
                        'wikidata_id' => $monument->wikidata_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Commons update job completed', [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Commons update job failed', [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update commons category and image data for a single monument.
     */
    private function updateMonumentCommonsData(Monument $monument, WikidataSparqlService $sparqlService): void
    {
        // Fetch commons data for this specific monument
        $commonsData = $this->fetchCommonsDataForMonument($monument->wikidata_id, $sparqlService);

        if (! $commonsData) {
            Log::debug('No commons data found for monument', [
                'wikidata_id' => $monument->wikidata_id,
            ]);

            return;
        }

        $updateData = [];
        $properties = $monument->properties ?? [];

        // Update commons category if we have it and it's missing
        if (! empty($commonsData['commons_category']) && empty($monument->commons_category)) {
            $updateData['commons_category'] = $commonsData['commons_category'];
        }

        // Update image in properties if we have it and it's missing
        if (! empty($commonsData['image']) && empty($properties['image'])) {
            $properties['image'] = $commonsData['image'];
            $updateData['properties'] = $properties;
        }

        // Update has_photos flag if we found an image
        if (! empty($commonsData['image'])) {
            $updateData['has_photos'] = true;
        }

        // Only update if we have something to update
        if (! empty($updateData)) {
            $monument->update($updateData);

            Log::debug('Updated commons data for monument', [
                'wikidata_id' => $monument->wikidata_id,
                'updated_fields' => array_keys($updateData),
            ]);
        }
    }

    /**
     * Fetch commons category and image data for a specific monument from Wikidata.
     */
    private function fetchCommonsDataForMonument(string $wikidataId, WikidataSparqlService $sparqlService): ?array
    {
        $query = $this->buildCommonsDataQuery($wikidataId);

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'WikiLovesMonumentsTurkey/1.0 (https://vikianitlariseviyor.tr; User_talk:Magurale")',
                'Accept' => 'application/sparql-results+json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->timeout(60)->retry(3, 2000)->asForm()->post('https://query.wikidata.org/sparql', [
                'query' => $query,
                'format' => 'json',
            ]);

            if (! $response->successful()) {
                Log::warning('Failed to fetch commons data from Wikidata', [
                    'wikidata_id' => $wikidataId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            return $this->processCommonsDataResponse($data);

        } catch (\Exception $e) {
            Log::error('Exception fetching commons data from Wikidata', [
                'wikidata_id' => $wikidataId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build SPARQL query to fetch commons category and image for a specific monument.
     */
    private function buildCommonsDataQuery(string $wikidataId): string
    {
        return "
        SELECT ?commonsCat ?image WHERE {
          wd:{$wikidataId} wdt:P17 wd:Q43.
          OPTIONAL { wd:{$wikidataId} wdt:P373 ?commonsCat. }
          OPTIONAL { wd:{$wikidataId} wdt:P18 ?image. }
        }
        ";
    }

    /**
     * Process the SPARQL response for commons data.
     */
    private function processCommonsDataResponse(array $data): ?array
    {
        if (! isset($data['results']['bindings']) || empty($data['results']['bindings'])) {
            return null;
        }

        $binding = $data['results']['bindings'][0];
        $commonsCategory = $binding['commonsCat']['value'] ?? null;
        $imageUri = $binding['image']['value'] ?? null;

        $result = [];

        if ($commonsCategory) {
            $result['commons_category'] = $commonsCategory;
        }

        if ($imageUri) {
            // Extract filename from URI
            $parts = explode('/', $imageUri);
            $imageFilename = urldecode(end($parts));

            if (! str_starts_with($imageFilename, 'File:')) {
                $imageFilename = 'File:'.$imageFilename;
            }

            $result['image'] = $imageFilename;
        }

        return empty($result) ? null : $result;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateMonumentsCommonsJob failed', [
            'offset' => $this->offset,
            'limit' => $this->limit,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
