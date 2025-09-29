<?php

namespace App\Services;

use App\Models\Monument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataSparqlService
{
    private const SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';

    private const USER_AGENT = 'WikiLovesMonumentsTurkey/1.0 (https://wlmtr.org; mailto:info@wlmtr.org)';

    /**
     * Last HTTP status returned by the SPARQL endpoint.
     */
    public ?int $lastHttpStatus = null;

    /**
     * Fetch monuments from Wikidata using SPARQL query.
     */
    public function fetchMonuments(int $offset = 0, int $limit = 1000): array
    {
        $query = $this->buildMonumentsQuery($offset, $limit);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->timeout(120)->retry(5, 5000)->asForm()->post(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            $this->lastHttpStatus = $response->status();

            if ($response->successful()) {
                $data = $response->json();

                // Check if data is null or empty
                if ($data === null || ! is_array($data)) {
                    Log::warning('Wikidata SPARQL query returned null or invalid data', [
                        'response_body' => $response->body(),
                    ]);

                    return [];
                }

                return $this->processMonumentsData($data);
            } else {
                Log::error('Wikidata SPARQL query failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            $this->lastHttpStatus = null;
            Log::error('Exception during Wikidata SPARQL query', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Return total count of monuments from SPARQL (authoritative count for the current query).
     */
    public function fetchMonumentsCount(): int
    {
        $query = '
        SELECT (COUNT(DISTINCT ?place) AS ?count) WHERE {
          ?place wdt:P17 wd:Q43; wdt:P11729 ?monumentId.
          MINUS {
            ?place wdt:P5816 ?v .
            FILTER(?v NOT IN (
              wd:Q56557159, wd:Q56557591, wd:Q55555088, wd:Q60539160,
              wd:Q63065035, wd:Q75505084, wd:Q27132179, wd:Q63187954,
              wd:Q111050392, wd:Q106379705, wd:Q117841865
            ))
          }
        }
        ';

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->timeout(60)->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);
            if (! $response->successful()) {
                return 0;
            }
            $data = $response->json();
            $val = $data['results']['bindings'][0]['count']['value'] ?? null;
            return $val !== null ? (int) $val : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Fetch a page of unique monument QIDs to verify completeness.
     * @return array<int,string> e.g., ['Q123', 'Q456']
     */
    public function fetchMonumentQids(int $offset = 0, int $limit = 1000): array
    {
        $query = '
        SELECT DISTINCT ?place WHERE {
          ?place wdt:P17 wd:Q43; wdt:P11729 ?monumentId.
          MINUS {
            ?place wdt:P5816 ?v .
            FILTER(?v NOT IN (
              wd:Q56557159, wd:Q56557591, wd:Q55555088, wd:Q60539160,
              wd:Q63065035, wd:Q75505084, wd:Q27132179, wd:Q63187954,
              wd:Q111050392, wd:Q106379705, wd:Q117841865
            ))
          }
        }
        LIMIT '.$limit.' OFFSET '.$offset.'
        ';

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->timeout(120)->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);
            if (! $response->successful()) {
                return [];
            }
            $data = $response->json();
            $qids = [];
            foreach ($data['results']['bindings'] ?? [] as $b) {
                $uri = $b['place']['value'] ?? null;
                if ($uri && preg_match('/Q\d+$/', $uri, $m)) {
                    $qids[] = $m[0];
                }
            }
            return $qids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Build the optimized SPARQL query for Turkish monuments.
     * This comprehensive query fetches all necessary data in a single request.
     */
    private function buildMonumentsQuery(int $offset = 0, int $limit = 500): string
    {
        return '
        SELECT 
          ?place 
          ?placeLabel 
          ?placeAltLabel 
          ?placeDescription 
          ?placeDescriptionEn 
          ?coordinates 
          ?instanceOf 
          ?instanceOfLabel 
          ?commonsCat 
          ?monumentId 
          ?enwiki 
          ?trwiki
          ?image
        WHERE {
          # Ülke: Türkiye ve P11729 niteliği olmalı
          ?place wdt:P17 wd:Q43;
                 wdt:P11729 ?monumentId.

          # P5816 filtresi (sadece listedekiler veya hiç olmayanlar)
          MINUS { 
            ?place wdt:P5816 ?v .
            FILTER(?v NOT IN (
              wd:Q56557159, wd:Q56557591, wd:Q55555088, wd:Q60539160, 
              wd:Q63065035, wd:Q75505084, wd:Q27132179, wd:Q63187954, 
              wd:Q111050392, wd:Q106379705, wd:Q117841865
            ))
          }

          # Koordinatlar
          OPTIONAL { ?place wdt:P625 ?coordinates. }

          # Commons category (P373)
          OPTIONAL { ?place wdt:P373 ?commonsCat. }

          # Görsel (P18)
          OPTIONAL { ?place wdt:P18 ?image. }

          # Türkçe diğer adlar (alias)
          OPTIONAL { 
            ?place skos:altLabel ?placeAltLabel.
            FILTER(LANG(?placeAltLabel) = "tr")
          }

          # Türkçe açıklama
          OPTIONAL {
            ?place schema:description ?placeDescription.
            FILTER(LANG(?placeDescription) = "tr")
          }

          # İngilizce açıklama (fallback)
          OPTIONAL {
            ?place schema:description ?placeDescriptionEn.
            FILTER(LANG(?placeDescriptionEn) = "en")
          }

          # Instance of (P31)
          OPTIONAL { ?place wdt:P31 ?instanceOf. }

          # enwiki ve trwiki sitelinks
          OPTIONAL { ?enwiki schema:about ?place ; schema:isPartOf <https://en.wikipedia.org/>. }
          OPTIONAL { ?trwiki schema:about ?place ; schema:isPartOf <https://tr.wikipedia.org/>. }

          # Türkçe label\'lar
          OPTIONAL { 
            ?place rdfs:label ?placeLabel .
            FILTER(LANG(?placeLabel) = "tr")
          }
          OPTIONAL {
            ?instanceOf rdfs:label ?instanceOfLabel .
            FILTER(LANG(?instanceOfLabel) = "tr")
          }
        }
        LIMIT '.$limit.' OFFSET '.$offset.'
        ';
    }

    /**
     * Process the SPARQL results into structured data.
     */
    private function processMonumentsData(array $data): array
    {
        $monuments = [];

        if (! isset($data['results']['bindings'])) {
            return $monuments;
        }

        foreach ($data['results']['bindings'] as $binding) {
            $monument = $this->processMonumentBinding($binding);
            if ($monument) {
                $monuments[] = $monument;
            }
        }

        return $monuments;
    }

    /**
     * Process a single monument binding from SPARQL results.
     * Now handles comprehensive data from the optimized query.
     */
    private function processMonumentBinding(array $binding): ?array
    {
        $placeUri = $binding['place']['value'] ?? null;
        if (! $placeUri) {
            return null;
        }

        $wikidataId = $this->extractWikidataId($placeUri);
        if (! $wikidataId) {
            return null;
        }

        $coordinates = $this->parseCoordinates($binding['coordinates']['value'] ?? null);

        // Instance of (P31)
        $instanceUri = $binding['instanceOf']['value'] ?? null;
        $instanceQid = $this->extractWikidataId($instanceUri);
        $instanceLabelTr = $binding['instanceOfLabel']['value'] ?? null;

        // Extract image information
        $imageUri = $binding['image']['value'] ?? null;
        $imageFilename = null;
        $hasPhotos = false;
        if ($imageUri) {
            $parts = explode('/', $imageUri);
            $imageFilename = urldecode(end($parts));
            if (! str_starts_with($imageFilename, 'File:')) {
                $imageFilename = 'File:'.$imageFilename;
            }
            $hasPhotos = true;
        }

        // Extract Wikipedia URLs
        $enwikiUrl = $binding['enwiki']['value'] ?? null;
        $trwikiUrl = $binding['trwiki']['value'] ?? null;
        $wikipediaUrl = $trwikiUrl ?: $enwikiUrl; // Prefer Turkish Wikipedia

        // Commons category
        $commonsCategory = $binding['commonsCat']['value'] ?? null;
        $commonsUrl = $commonsCategory ? "https://commons.wikimedia.org/wiki/Category:{$commonsCategory}" : null;

        // Turkish aliases - combine them if multiple
        $aliases = [];
        if (isset($binding['placeAltLabel']['value'])) {
            $aliases[] = $binding['placeAltLabel']['value'];
        }

        return [
            'wikidata_id' => $wikidataId,
            'name_tr' => $this->cleanLabel($binding['placeLabel']['value'] ?? null),
            'description_tr' => $this->cleanLabel($binding['placeDescription']['value'] ?? null),
            'description_en' => $this->cleanLabel($binding['placeDescriptionEn']['value'] ?? null),
            'aka' => ! empty($aliases) ? implode(', ', $aliases) : null,
            'kulturenvanteri_id' => $binding['monumentId']['value'] ?? null,
            'commons_category' => $commonsCategory,
            'latitude' => $coordinates['lat'] ?? null,
            'longitude' => $coordinates['lng'] ?? null,
            'heritage_status' => null,
            'construction_date' => null,
            'architect' => null,
            'style' => null,
            'material' => null,
            'address' => null,
            'city' => null,
            'district' => null,
            'province' => null,
            'commons_url' => $commonsUrl,
            'wikipedia_url' => $wikipediaUrl,
            'wikidata_url' => $placeUri,
            'has_photos' => $hasPhotos,
            'properties' => array_filter([
                'instance_of' => $instanceQid,
                'instance_of_label_tr' => $this->cleanLabel($instanceLabelTr),
                'image_filename' => $imageFilename,
                'enwiki_url' => $enwikiUrl,
                'trwiki_url' => $trwikiUrl,
            ]),
        ];
    }

    /**
     * Extract Wikidata ID from URI.
     */
    private function extractWikidataId(?string $uri): ?string
    {
        if (! $uri) {
            return null;
        }

        if (preg_match('/Q\d+$/', $uri, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Clean label by removing URI prefixes and returning null if it's still a URI.
     */
    private function cleanLabel(?string $label): ?string
    {
        if (! $label) {
            return null;
        }

        // If it's still a URI, return null so we can fall back to Wikidata ID
        if (str_starts_with($label, 'http://') || str_starts_with($label, 'https://')) {
            return null;
        }

        return $label;
    }

    /**
     * Parse coordinates from SPARQL result.
     */
    private function parseCoordinates(?string $coordinates): ?array
    {
        if (! $coordinates) {
            return null;
        }

        // Expected format: "Point(29.0322 41.0082)"
        if (preg_match('/Point\(([\d.-]+)\s+([\d.-]+)\)/', $coordinates, $matches)) {
            return [
                'lng' => (float) $matches[1],
                'lat' => (float) $matches[2],
            ];
        }

        return null;
    }

    /**
     * Sync monuments data to database using the optimized comprehensive approach.
     * This method now handles all the data from the unified SPARQL query.
     */
    public function syncMonumentsToDatabase(int $batchSize = 500, ?int $maxBatches = null, ?callable $reportProgress = null): int
    {
        $totalSynced = 0;
        $totalUpdated = 0;
        $totalNew = 0;
        $totalErrors = 0;
        $offset = 0;
        $batchNumber = 1;

        while (true) {
            Log::info("🔄 Fetching batch {$batchNumber} (offset: {$offset}, limit: {$batchSize})");
            if ($reportProgress !== null) {
                try {
                    $reportProgress('start_batch', [
                        'batch' => $batchNumber,
                        'offset' => $offset,
                        'limit' => $batchSize,
                    ]);
                } catch (\Throwable $e) {
                    // ignore progress reporting errors
                }
            }

            $monuments = $this->fetchMonuments($offset, $batchSize);

            if (empty($monuments)) {
                Log::info('📭 No more monuments to process');
                break;
            }

            $batchSynced = 0;
            $batchUpdated = 0;
            $batchNew = 0;
            $batchErrors = 0;

            foreach ($monuments as $monumentData) {
                try {
                    // Check if monument exists
                    $existingMonument = Monument::where('wikidata_id', $monumentData['wikidata_id'])->first();
                    $isNew = ! $existingMonument;

                    // Avoid overwriting existing values with nulls by filtering nulls out
                    $nonNullAttributes = array_filter($monumentData, static function ($value) {
                        return $value !== null;
                    });

                    $monument = Monument::updateOrCreate(
                        ['wikidata_id' => $monumentData['wikidata_id']],
                        array_merge($nonNullAttributes, [
                            'last_synced_at' => now(),
                        ])
                    );

                    // Enrich with location hierarchy if missing (batch this later for efficiency)
                    if (empty($monument->location_hierarchy_tr)) {
                        try {
                            $hierarchy = $this->fetchLocationHierarchyString($monument->wikidata_id);
                            if (! empty($hierarchy)) {
                                $monument->location_hierarchy_tr = $hierarchy;
                                $monument->save();
                            }
                        } catch (\Throwable $e) {
                            // Soft-fail; avoid blocking sync on hierarchy enrichment
                            Log::debug("⚠️ Could not fetch location hierarchy for {$monument->wikidata_id}: {$e->getMessage()}");
                        }
                    }

                    $batchSynced++;
                    if ($isNew) {
                        $batchNew++;
                    } else {
                        $batchUpdated++;
                    }

                } catch (\Exception $e) {
                    $batchErrors++;
                    Log::error('❌ Failed to sync monument', [
                        'wikidata_id' => $monumentData['wikidata_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalSynced += $batchSynced;
            $totalUpdated += $batchUpdated;
            $totalNew += $batchNew;
            $totalErrors += $batchErrors;

            // Prepare up to 3 example monuments from this batch for progress reporting
            $examples = [];
            foreach (array_slice($monuments, 0, 3) as $m) {
                $examples[] = [
                    'wikidata_id' => $m['wikidata_id'] ?? null,
                    'name_tr' => $m['name_tr'] ?? null,
                    'kulturenvanteri_id' => $m['kulturenvanteri_id'] ?? null,
                ];
            }

            Log::info("✅ Batch {$batchNumber} completed", [
                'synced' => $batchSynced,
                'new' => $batchNew,
                'updated' => $batchUpdated,
                'errors' => $batchErrors,
                'total_so_far' => $totalSynced,
                'http_status' => $this->lastHttpStatus,
            ]);

            if ($reportProgress !== null) {
                try {
                    $reportProgress('end_batch', [
                        'batch' => $batchNumber,
                        'synced' => $batchSynced,
                        'new' => $batchNew,
                        'updated' => $batchUpdated,
                        'errors' => $batchErrors,
                        'total' => $totalSynced,
                        'http_status' => $this->lastHttpStatus,
                        'examples' => $examples,
                    ]);
                } catch (\Throwable $e) {
                    // ignore progress reporting errors
                }
            }

            $offset += $batchSize;
            $batchNumber++;

            // Add a small delay to avoid overwhelming Wikidata
            sleep(1); // Reduced from 2 seconds since we're doing fewer requests

            // Respect max batches if provided
            if ($maxBatches !== null && ($batchNumber - 1) >= $maxBatches) {
                Log::info("🛑 Reached maximum batches limit: {$maxBatches}");
                break;
            }

            // Stop if the endpoint returned fewer than requested (likely no more pages)
            if (count($monuments) < $batchSize) {
                Log::info('📄 Last batch processed (fewer results than batch size)');
                break;
            }
        }

        Log::info('🎯 Unified monuments sync completed successfully', [
            'total_synced' => $totalSynced,
            'total_new' => $totalNew,
            'total_updated' => $totalUpdated,
            'total_errors' => $totalErrors,
            'batches_processed' => $batchNumber - 1,
            'batch_size' => $batchSize,
            'max_batches' => $maxBatches,
        ]);

        if ($reportProgress !== null) {
            try {
                $reportProgress('complete', [
                    'total_synced' => $totalSynced,
                    'total_new' => $totalNew,
                    'total_updated' => $totalUpdated,
                    'total_errors' => $totalErrors,
                    'batches_processed' => $batchNumber - 1,
                    'batch_size' => $batchSize,
                    'max_batches' => $maxBatches,
                ]);
            } catch (\Throwable $e) {
                // ignore progress reporting errors
            }
        }

        return $totalSynced;
    }

    /**
     * Build SPARQL to retrieve up to three P131 levels for a given Q-code.
     */
    private function buildP131HierarchyQuery(string $wikidataId): string
    {
        $wikidataId = strtoupper($wikidataId);

        return <<<SPARQL
SELECT ?level ?entity ?entityLabel WHERE {
  VALUES ?start { wd:$wikidataId }
  {
    ?start wdt:P131 ?entity .
    BIND(1 AS ?level)
  }
  UNION
  {
    ?start wdt:P131/wdt:P131 ?entity .
    BIND(2 AS ?level)
  }
  UNION
  {
    ?start wdt:P131/wdt:P131/wdt:P131 ?entity .
    BIND(3 AS ?level)
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "tr,en" }
}
ORDER BY ?level
SPARQL;
    }

    /**
     * Fetch ordered P131 hierarchy labels (closest first to broadest) for a Q-code.
     * Returns an array like ["Yeşilyurt", "Malatya", "Türkiye"].
     *
     * @return array<int,string>
     */
    public function fetchP131HierarchyLabels(string $wikidataId): array
    {
        if (! preg_match('/^Q\d+$/i', $wikidataId)) {
            return [];
        }

        $query = $this->buildP131HierarchyQuery($wikidataId);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->timeout(120)->retry(5, 5000)->asForm()->post(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            $bindings = $data['results']['bindings'] ?? [];
            $labels = [];
            foreach ($bindings as $b) {
                $label = $b['entityLabel']['value'] ?? null;
                if (is_string($label) && $label !== '') {
                    $labels[] = $label;
                }
            }

            // De-duplicate while preserving order
            $labels = array_values(array_unique($labels));

            return $labels;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Convenience: return a single string joined by comma for TR hierarchy.
     */
    public function fetchLocationHierarchyString(string $wikidataId): ?string
    {
        $labels = $this->fetchP131HierarchyLabels($wikidataId);
        if (empty($labels)) {
            return null;
        }

        return implode(', ', $labels);
    }

    /**
     * Fetch additional details for a specific monument.
     */
    public function fetchMonumentDetails(string $wikidataId): ?array
    {
        $query = $this->buildMonumentDetailsQuery($wikidataId);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processMonumentDetails($data);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch monument details', [
                'wikidata_id' => $wikidataId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Build SPARQL query for detailed monument information.
     */
    private function buildMonumentDetailsQuery(string $wikidataId): string
    {
        return "
        SELECT ?property ?propertyLabel ?value ?valueLabel WHERE {
          wd:{$wikidataId} ?property ?value.
          ?property wikibase:directClaim ?p.
          ?property rdfs:label ?propertyLabel.
          FILTER(LANG(?propertyLabel) = 'en')
          
          OPTIONAL {
            ?value rdfs:label ?valueLabel.
            FILTER(LANG(?valueLabel) = 'tr')
          }
        }
        LIMIT 100
        ";
    }

    /**
     * Process monument details from SPARQL results.
     */
    private function processMonumentDetails(array $data): array
    {
        $details = [];

        if (! isset($data['results']['bindings'])) {
            return $details;
        }

        foreach ($data['results']['bindings'] as $binding) {
            $property = $binding['propertyLabel']['value'] ?? null;
            $value = $binding['valueLabel']['value'] ?? $binding['value']['value'] ?? null;

            if ($property && $value) {
                $details[$property] = $value;
            }
        }

        return $details;
    }

    /**
     * Fetch a human-readable label for a Wikidata Q-code (e.g., Q406).
     * Uses a static cache to avoid repeated lookups.
     */
    public static function getLabelForQCode(string $qcode): ?string
    {
        static $labelCache = [];
        if (isset($labelCache[$qcode])) {
            return $labelCache[$qcode];
        }
        if (! preg_match('/^Q\\d+$/', $qcode)) {
            return $qcode; // Not a Q-code, return as is
        }
        $url = "https://www.wikidata.org/wiki/Special:EntityData/{$qcode}.json";
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
            if ($response->successful()) {
                $data = $response->json();
                $entity = $data['entities'][$qcode] ?? null;
                if ($entity && isset($entity['labels'])) {
                    // Prefer Turkish, then English, then any
                    $labels = $entity['labels'];
                    if (isset($labels['tr'])) {
                        $label = $labels['tr']['value'];
                    } elseif (isset($labels['en'])) {
                        $label = $labels['en']['value'];
                    } else {
                        $label = reset($labels)['value'] ?? $qcode;
                    }
                    $labelCache[$qcode] = $label;

                    return $label;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors, fallback to Q-code
        }
        $labelCache[$qcode] = $qcode;

        return $qcode;
    }

    /**
     * Fetch full Wikidata entity data for a Q-code (labels, descriptions, aliases, sitelinks, claims).
     * Returns the raw entity array or null on failure.
     */
    public static function getEntityData(string $qcode): ?array
    {
        if (! preg_match('/^Q\d+$/', $qcode)) {
            return null;
        }

        // Use Special:EntityData with ids= to allow future batching if needed
        $url = 'https://www.wikidata.org/wiki/Special:EntityData/'.$qcode.'.json';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get($url, [
                // Include Turkish and English; Special:EntityData already returns all, but this is future-proof
                'languages' => 'tr|en',
                'props' => 'labels|descriptions|aliases|sitelinks|claims',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $entity = $data['entities'][$qcode] ?? null;
                if (is_array($entity)) {
                    return $entity;
                }
            } elseif ($response->status() === 429) {
                // Rate limited - return null to let caller handle delay
                \Illuminate\Support\Facades\Log::warning("Rate limited for {$qcode}");

                return null;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Exception fetching {$qcode}: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Fetch all Turkish provinces from Wikidata.
     */
    public function fetchTurkishProvinces(): array
    {
        $query = '
        SELECT ?province ?provinceLabel WHERE {
          ?province wdt:P17 wd:Q43.
          ?province wdt:P31 wd:Q15089.
          SERVICE wikibase:label {
            bd:serviceParam wikibase:language "tr,en".
          }
        }
        ORDER BY ?provinceLabel
        ';

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->timeout(15)->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $provinces = [];

                foreach ($data['results']['bindings'] ?? [] as $binding) {
                    $label = $binding['provinceLabel']['value'] ?? null;
                    if ($label && ! str_starts_with($label, 'http')) {
                        $provinces[] = $label;
                    }
                }

                return array_unique($provinces);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Turkish provinces', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Fetch monument categories from Wikidata.
     * Uses a predefined list of common monument types for better performance.
     */
    public function fetchMonumentCategories(): array
    {
        // Predefined list of common monument types in Turkey
        $monumentTypes = [
            'Q570116' => 'anıt', // monument
            'Q811979' => 'arkeolojik sit', // archaeological site
            'Q16970' => 'kilise', // church
            'Q12280' => 'cami', // mosque
            'Q16917' => 'saray', // palace
            'Q16918' => 'kale', // castle
            'Q16919' => 'kervansaray', // caravanserai
            'Q16920' => 'hamam', // bathhouse
            'Q16921' => 'medrese', // madrasa
            'Q16922' => 'türbe', // tomb
            'Q16923' => 'çeşme', // fountain
            'Q16924' => 'köprü', // bridge
            'Q16925' => 'kule', // tower
            'Q16926' => 'kapı', // gate
            'Q16927' => 'sütun', // column
            'Q16928' => 'heykel', // statue
            'Q16929' => 'müze', // museum
            'Q16930' => 'kütüphane', // library
            'Q16931' => 'okul', // school
            'Q16932' => 'hastane', // hospital
        ];

        $categories = [];

        foreach ($monumentTypes as $qid => $nameTr) {
            // Get English name using the existing method
            $nameEn = self::getLabelForQCode($qid);
            if ($nameEn === $qid) {
                $nameEn = $nameTr; // Fallback to Turkish if English not found
            }

            $categories[] = [
                'wikidata_id' => $qid,
                'name_tr' => $nameTr,
                'name_en' => $nameEn,
                'description_tr' => null,
                'description_en' => null,
            ];
        }

        return $categories;
    }

    /**
     * Fetch monuments with images from Wikidata using the provided SPARQL query.
     * This method tests direct image display from Wikimedia Commons without storing locally.
     */
    public function fetchMonumentsWithImages(): array
    {
        $query = $this->buildMonumentsWithImagesQuery();

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processMonumentsWithImagesData($data);
            } else {
                Log::error('Wikidata SPARQL query for monuments with images failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception during Wikidata SPARQL query for monuments with images', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Build the SPARQL query for Turkish monuments with images.
     */
    private function buildMonumentsWithImagesQuery(): string
    {
        return '
        SELECT ?item ?itemLabel ?image ?p11729 WHERE {
          ?item wdt:P11729 ?p11729;      # KE ID mevcut
                wdt:P18 ?image;          # Görsel mevcut
                wdt:P17 wd:Q43.          # Ülkesi Türkiye

          SERVICE wikibase:label { bd:serviceParam wikibase:language "tr,en". }
        }
        LIMIT 20
        ';
    }

    /**
     * Process the SPARQL results for monuments with images.
     */
    private function processMonumentsWithImagesData(array $data): array
    {
        $monuments = [];

        if (! isset($data['results']['bindings'])) {
            return $monuments;
        }

        foreach ($data['results']['bindings'] as $binding) {
            $monument = $this->processMonumentWithImageBinding($binding);
            if ($monument) {
                $monuments[] = $monument;
            }
        }

        return $monuments;
    }

    /**
     * Process a single monument with image binding from SPARQL results.
     */
    private function processMonumentWithImageBinding(array $binding): ?array
    {
        $itemUri = $binding['item']['value'] ?? null;
        if (! $itemUri) {
            return null;
        }

        $wikidataId = $this->extractWikidataId($itemUri);
        if (! $wikidataId) {
            return null;
        }

        $imageValue = $binding['image']['value'] ?? null;
        $imageFilename = null;
        $imageUrl = null;

        if ($imageValue) {
            // Extract filename from URL
            $parts = explode('/', $imageValue);
            $imageFilename = urldecode(end($parts));

            // Ensure it starts with 'File:' prefix
            if (! str_starts_with($imageFilename, 'File:')) {
                $imageFilename = 'File:'.$imageFilename;
            }

            // Generate Wikimedia Commons URLs for different sizes
            $imageUrl = $this->generateCommonsImageUrl($imageFilename);
        }

        return [
            'wikidata_id' => $wikidataId,
            'name' => $binding['itemLabel']['value'] ?? 'Unnamed Monument',
            'ke_id' => $binding['p11729']['value'] ?? null,
            'image_filename' => $imageFilename,
            'image_urls' => $imageUrl,
            'wikidata_url' => $itemUri,
        ];
    }

    /**
     * Generate Wikimedia Commons image URLs for different sizes.
     */
    private function generateCommonsImageUrl(string $filename): array
    {
        // Remove 'File:' prefix if present for URL generation
        $cleanFilename = str_replace('File:', '', $filename);

        // Proper URL encoding for Wikimedia Commons
        // Replace spaces with %20 instead of +, and handle other special characters
        $encodedFilename = str_replace(' ', '%20', $cleanFilename);
        $encodedFilename = rawurlencode($cleanFilename);

        return [
            'thumbnail' => "https://commons.wikimedia.org/wiki/Special:FilePath/{$encodedFilename}?width=300",
            'medium' => "https://commons.wikimedia.org/wiki/Special:FilePath/{$encodedFilename}?width=800",
            'large' => "https://commons.wikimedia.org/wiki/Special:FilePath/{$encodedFilename}?width=1200",
            'original' => "https://commons.wikimedia.org/wiki/Special:FilePath/{$encodedFilename}",
            'commons_page' => "https://commons.wikimedia.org/wiki/File:{$encodedFilename}",
        ];
    }

    /**
     * Fetch all monuments with images from Wikidata for photo sync.
     * This method returns more detailed image information for database storage.
     */
    public function fetchAllMonumentsWithImages(?int $limit = null): array
    {
        $query = $this->buildAllMonumentsWithImagesQuery($limit);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/sparql-results+json',
            ])->get(self::SPARQL_ENDPOINT, [
                'query' => $query,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processAllMonumentsWithImagesData($data);
            } else {
                Log::error('Wikidata SPARQL query for all monuments with images failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception during Wikidata SPARQL query for all monuments with images', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Build the SPARQL query for all Turkish monuments with images.
     */
    private function buildAllMonumentsWithImagesQuery(?int $limit = null): string
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
        SELECT ?item ?itemLabel ?image ?p11729 WHERE {
          ?item wdt:P11729 ?p11729;      # KE ID mevcut
                wdt:P18 ?image;          # Görsel mevcut
                wdt:P17 wd:Q43.          # Ülkesi Türkiye

          SERVICE wikibase:label { bd:serviceParam wikibase:language \"tr,en\". }
        }
        ORDER BY ?itemLabel
        {$limitClause}
        ";
    }

    /**
     * Process the SPARQL results for all monuments with images.
     */
    private function processAllMonumentsWithImagesData(array $data): array
    {
        $monuments = [];

        if (! isset($data['results']['bindings'])) {
            return $monuments;
        }

        foreach ($data['results']['bindings'] as $binding) {
            $monument = $this->processAllMonumentWithImageBinding($binding);
            if ($monument) {
                $monuments[] = $monument;
            }
        }

        return $monuments;
    }

    /**
     * Process a single monument with image binding for comprehensive photo sync.
     */
    private function processAllMonumentWithImageBinding(array $binding): ?array
    {
        $itemUri = $binding['item']['value'] ?? null;
        if (! $itemUri) {
            return null;
        }

        $wikidataId = $this->extractWikidataId($itemUri);
        if (! $wikidataId) {
            return null;
        }

        $imageValue = $binding['image']['value'] ?? null;
        $images = [];

        if ($imageValue) {
            // Extract filename from URL
            $parts = explode('/', $imageValue);
            $imageFilename = urldecode(end($parts));

            // Ensure it starts with 'File:' prefix
            if (! str_starts_with($imageFilename, 'File:')) {
                $imageFilename = 'File:'.$imageFilename;
            }

            // Generate Wikimedia Commons URLs for different sizes
            $imageUrls = $this->generateCommonsImageUrl($imageFilename);

            $images[] = [
                'filename' => $imageFilename,
                'title' => $binding['itemLabel']['value'] ?? 'Unnamed Monument',
                'description' => null, // Could be enhanced with more SPARQL queries
                'photographer' => null, // Could be enhanced with more SPARQL queries
                'license' => null, // Could be enhanced with more SPARQL queries
                'license_shortname' => null,
                'date_taken' => null, // Could be enhanced with more SPARQL queries
                'urls' => $imageUrls,
            ];
        }

        return [
            'wikidata_id' => $wikidataId,
            'name' => $binding['itemLabel']['value'] ?? 'Unnamed Monument',
            'ke_id' => $binding['p11729']['value'] ?? null,
            'images' => $images,
            'wikidata_url' => $itemUri,
        ];
    }
}
