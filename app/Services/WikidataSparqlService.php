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
     * Fetch monuments from Wikidata using SPARQL query.
     */
    public function fetchMonuments(): array
    {
        $query = $this->buildMonumentsQuery();

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

                return $this->processMonumentsData($data);
            } else {
                Log::error('Wikidata SPARQL query failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception during Wikidata SPARQL query', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Build the SPARQL query for Turkish monuments.
     */
    private function buildMonumentsQuery(): string
    {
        return '
        SELECT DISTINCT
          ?place ?placeLabel ?coordinates
          ?descriptionTr ?descriptionEn
          ?heritageStatus ?heritageStatusLabel
          ?constructionDate ?architect ?architectLabel
          ?style ?styleLabel ?material ?materialLabel
          ?address
          ?city ?cityLabel ?district ?districtLabel ?province ?provinceLabel
          ?commonsCategory ?image ?adminLabelTr
        WHERE {
          ?place wdt:P17 wd:Q43.
          OPTIONAL { ?place wdt:P625 ?coordinates. }
          OPTIONAL { ?place schema:description ?descriptionTr FILTER(LANG(?descriptionTr) = "tr") }
          OPTIONAL { ?place schema:description ?descriptionEn FILTER(LANG(?descriptionEn) = "en") }
          OPTIONAL { ?place wdt:P1435 ?heritageStatus. }
          OPTIONAL { ?place wdt:P571 ?constructionDate. }
          OPTIONAL { ?place wdt:P84 ?architect. }
          OPTIONAL { ?place wdt:P149 ?style. }
          OPTIONAL { ?place wdt:P186 ?material. }
          OPTIONAL { ?place wdt:P6375 ?address. }
          OPTIONAL { ?place wdt:P131 ?city. }
          OPTIONAL { ?place wdt:P131 ?district. }
          OPTIONAL { ?place wdt:P131 ?province. }
          OPTIONAL { ?place wdt:P18 ?image. }
          OPTIONAL { ?place wdt:P373 ?commonsCategory. }
          SERVICE wikibase:label {
            bd:serviceParam wikibase:language "[AUTO_LANGUAGE],tr,en".
          }
          BIND(COALESCE(?districtLabel, ?cityLabel, ?provinceLabel) AS ?adminLabelTr)
        }
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

        $imageValue = $binding['image']['value'] ?? null;
        $imageFilename = null;
        if ($imageValue) {
            // Extract filename from URL or take literal
            $parts = explode('/', $imageValue);
            $imageFilename = urldecode(end($parts));
            // Ensure it starts with 'File:' prefix
            if (! str_starts_with($imageFilename, 'File:')) {
                $imageFilename = 'File:'.$imageFilename;
            }
        }

        return [
            'wikidata_id' => $wikidataId,
            'name_tr' => $binding['placeLabel']['value'] ?? null,
            'description_tr' => $binding['descriptionTr']['value'] ?? null,
            'description_en' => $binding['descriptionEn']['value'] ?? null,
            'latitude' => $coordinates['lat'] ?? null,
            'longitude' => $coordinates['lng'] ?? null,
            'heritage_status' => $this->cleanLabel($binding['heritageStatusLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['heritageStatus']['value'] ?? null),
            'construction_date' => $binding['constructionDate']['value'] ?? null,
            'architect' => $this->cleanLabel($binding['architectLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['architect']['value'] ?? null),
            'style' => $this->cleanLabel($binding['styleLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['style']['value'] ?? null),
            'material' => $this->cleanLabel($binding['materialLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['material']['value'] ?? null),
            'address' => $binding['address']['value'] ?? null,
            'city' => $this->cleanLabel($binding['cityLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['city']['value'] ?? null),
            'district' => $this->cleanLabel($binding['districtLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['district']['value'] ?? null),
            'province' => $this->cleanLabel($binding['provinceLabel']['value'] ?? null) ?? $this->extractWikidataId($binding['province']['value'] ?? null),
            'commons_url' => isset($binding['commonsCategory']['value']) ? 'https://commons.wikimedia.org/wiki/Category:'.basename($binding['commonsCategory']['value']) : null,
            'wikipedia_url' => $binding['wikipediaUrl']['value'] ?? null,
            'wikidata_url' => $placeUri,
            'has_photos' => $imageFilename !== null || isset($binding['commonsCategory']['value']),
            'properties' => array_filter([
                'image' => $imageFilename,
                'commons_category' => $binding['commonsCategory']['value'] ?? null,
                'admin_label_tr' => $binding['adminLabelTr']['value'] ?? null,
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
     * Sync monuments data to database.
     */
    public function syncMonumentsToDatabase(): int
    {
        $monuments = $this->fetchMonuments();
        $syncedCount = 0;

        foreach ($monuments as $monumentData) {
            try {
                $monument = Monument::updateOrCreate(
                    ['wikidata_id' => $monumentData['wikidata_id']],
                    array_merge($monumentData, [
                        'last_synced_at' => now(),
                    ])
                );

                // Enrich with location hierarchy if missing
                if (empty($monument->location_hierarchy_tr)) {
                    try {
                        $hierarchy = $this->fetchLocationHierarchyString($monument->wikidata_id);
                        if (! empty($hierarchy)) {
                            $monument->location_hierarchy_tr = $hierarchy;
                            $monument->save();
                        }
                    } catch (\Throwable $e) {
                        // Soft-fail; avoid blocking sync on hierarchy enrichment
                    }
                }

                $syncedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to sync monument', [
                    'wikidata_id' => $monumentData['wikidata_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Monuments sync completed', [
            'total_fetched' => count($monuments),
            'synced_count' => $syncedCount,
        ]);

        return $syncedCount;
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
            ])->timeout(15)->get(self::SPARQL_ENDPOINT, [
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
            $response = \Illuminate\Support\Facades\Http::timeout(8)->withHeaders([
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
            }
        } catch (\Throwable $e) {
            // Soft-fail; caller can ignore
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
                    if ($label && !str_starts_with($label, 'http')) {
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
     */
    public function fetchMonumentCategories(): array
    {
        $query = '
        SELECT DISTINCT ?category ?categoryLabel WHERE {
          ?monument wdt:P17 wd:Q43.
          ?monument wdt:P31 ?category.
          SERVICE wikibase:label {
            bd:serviceParam wikibase:language "tr,en".
          }
        }
        ORDER BY ?categoryLabel
        LIMIT 50
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
                $categories = [];

                foreach ($data['results']['bindings'] ?? [] as $binding) {
                    $wikidataId = $this->extractWikidataId($binding['category']['value'] ?? '');
                    $label = $binding['categoryLabel']['value'] ?? null;

                    if ($wikidataId && $label && !str_starts_with($label, 'http')) {
                        $categories[] = [
                            'wikidata_id' => $wikidataId,
                            'name_tr' => $label,
                            'name_en' => $label,
                            'description_tr' => null,
                            'description_en' => null,
                        ];
                    }
                }

                return $categories;
            } else {
                Log::error('SPARQL query failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch monument categories', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
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
