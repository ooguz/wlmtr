<?php

namespace App\Services;

use App\Models\Monument;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        SELECT DISTINCT ?place ?placeLabel ?coordinates ?description ?heritageStatus ?heritageStatusLabel ?constructionDate ?architect ?architectLabel ?style ?styleLabel ?material ?materialLabel ?address ?city ?cityLabel ?district ?districtLabel ?province ?provinceLabel ?commonsCategory ?wikipediaUrl ?commonsUrl WHERE {
          ?place wdt:P17 wd:Q43; # Country (P17) Turkey (Q43)
                 wdt:P11729 _:dummy. # P11729 property must exist (monument status)
          
          OPTIONAL { ?place wdt:P625 ?coordinates. } # Coordinates (P625)
          OPTIONAL { ?place schema:description ?description FILTER(LANG(?description) = "tr") }
          OPTIONAL { ?place wdt:P1435 ?heritageStatus. } # Heritage status
          OPTIONAL { ?place wdt:P571 ?constructionDate. } # Construction date
          OPTIONAL { ?place wdt:P84 ?architect. } # Architect
          OPTIONAL { ?place wdt:P149 ?style. } # Architectural style
          OPTIONAL { ?place wdt:P186 ?material. } # Material
          OPTIONAL { ?place wdt:P6375 ?address. } # Address
          OPTIONAL { ?place wdt:P131 ?city. } # City
          OPTIONAL { ?place wdt:P131 ?district. } # District
          OPTIONAL { ?place wdt:P131 ?province. } # Province
          OPTIONAL { ?place wdt:P373 ?commonsCategory. } # Commons category
          OPTIONAL { ?place wdt:P856 ?wikipediaUrl. } # Website (Wikipedia)
          OPTIONAL { ?place wdt:P373 ?commonsUrl. } # Commons category
          
          SERVICE wikibase:label { 
            bd:serviceParam wikibase:language "tr,en,tr,en". 
          }
        }
        LIMIT 1000
        ';
    }

    /**
     * Process the SPARQL results into structured data.
     */
    private function processMonumentsData(array $data): array
    {
        $monuments = [];
        
        if (!isset($data['results']['bindings'])) {
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
        if (!$placeUri) {
            return null;
        }

        $wikidataId = $this->extractWikidataId($placeUri);
        if (!$wikidataId) {
            return null;
        }

        $coordinates = $this->parseCoordinates($binding['coordinates']['value'] ?? null);
        
        return [
            'wikidata_id' => $wikidataId,
            'name_tr' => $binding['placeLabel']['value'] ?? null,
            'description_tr' => $binding['description']['value'] ?? null,
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
            'commons_category' => $binding['commonsCategory']['value'] ?? null,
            'wikipedia_url' => $binding['wikipediaUrl']['value'] ?? null,
            'commons_url' => $binding['commonsUrl']['value'] ?? null,
            'wikidata_url' => $placeUri,
        ];
    }

    /**
     * Extract Wikidata ID from URI.
     */
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

    /**
     * Clean label by removing URI prefixes and returning null if it's still a URI.
     */
    private function cleanLabel(?string $label): ?string
    {
        if (!$label) {
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
        if (!$coordinates) {
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
        
        if (!isset($data['results']['bindings'])) {
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
        if (!preg_match('/^Q\\d+$/', $qcode)) {
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
} 