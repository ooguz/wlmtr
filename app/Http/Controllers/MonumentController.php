<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Monument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MonumentController extends Controller
{
    /**
     * Resolve monument by ID or Wikidata ID.
     */
    private function resolveMonument(string $identifier): Monument
    {
        // Check if it's a Wikidata ID (starts with Q followed by digits)
        if (preg_match('/^Q\d+$/i', $identifier)) {
            $monument = Monument::where('wikidata_id', strtoupper($identifier))->first();
            if (! $monument) {
                abort(404, 'Monument not found');
            }

            return $monument;
        }

        // Check if it's a numeric ID
        if (is_numeric($identifier)) {
            $monument = Monument::find($identifier);
            if (! $monument) {
                abort(404, 'Monument not found');
            }

            return $monument;
        }

        // If neither, try to find by ID anyway (for backward compatibility)
        $monument = Monument::find($identifier);
        if (! $monument) {
            abort(404, 'Monument not found');
        }

        return $monument;
    }

    /**
     * Display the monuments map view.
     */
    public function map(Request $request): View
    {
        // Frontend fetches markers via API using viewport bounds; avoid heavy preload here
        return view('monuments.map');
    }

    /**
     * Display the monuments list view.
     */
    public function list(Request $request, \App\Services\WikimediaCommonsService $commonsService): View
    {
        $query = Monument::with(['photos', 'categories']);

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->string('search'));
        }

        if ($request->filled('province')) {
            $query->byProvince($request->get('province'));
        }

        if ($request->filled('city')) {
            $query->byCity($request->get('city'));
        }

        if ($request->filled('heritage_status')) {
            $query->byHeritageStatus($request->get('heritage_status'));
        }

        if ($request->filled('has_photos')) {
            if ($request->get('has_photos') === '1') {
                $query->withPhotos();
            } else {
                $query->withoutPhotos();
            }
        }

        if ($request->filled('category')) {
            $categoryRaw = (string) $request->get('category');
            $categoryId = ctype_digit($categoryRaw) ? (int) $categoryRaw : null;
            $selectedCategory = $categoryId ? \App\Models\Category::find($categoryId) : null;

            $connection = $query->getModel()->getConnection();
            $driver = $connection->getDriverName();

            $qid = null;
            $labelLike = null;
            if ($selectedCategory && ! empty($selectedCategory->wikidata_id)) {
                $qid = $selectedCategory->wikidata_id;
            } elseif (preg_match('/^Q\d+$/i', $categoryRaw)) {
                $qid = strtoupper($categoryRaw);
            } elseif (is_string($categoryRaw) && $categoryRaw !== '' && ! ctype_digit($categoryRaw)) {
                $labelLike = '%'.strtolower($categoryRaw).'%';
            }

            // Build filter: prefer relational when valid id; also match JSON instance_of / physical_feature or label
            $query->where(function ($q) use ($categoryId, $selectedCategory, $driver, $qid, $labelLike) {
                if ($selectedCategory && $categoryId) {
                    $q->orWhereHas('categories', function ($cq) use ($categoryId) {
                        $cq->where('categories.id', $categoryId);
                    });
                }
                if ($qid) {
                    $q->orWhere(function ($q2) use ($driver, $qid) {
                        if ($driver === 'mysql') {
                            $q2->where('properties->instance_of', $qid)
                                ->orWhere('properties->physical_feature', $qid)
                                ->orWhereRaw("JSON_SEARCH(properties, 'one', ?) IS NOT NULL", [$qid]);
                        } else {
                            $like = '%"'.$qid.'"%';
                            $q2->where('properties', 'like', $like);
                        }
                    });
                }
                if ($labelLike) {
                    if ($driver === 'mysql') {
                        $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(properties, '$.instance_of_label_tr'))) LIKE ?", [$labelLike]);
                    } else {
                        $q->orWhere('properties', 'like', '%instance_of_label_tr%')
                            ->orWhere('properties', 'like', '%'.trim($labelLike, '%').'%');
                    }
                }
            });
        }

        // Distance-based filtering and ordering
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->get('lat');
            $lng = (float) $request->get('lng');
            $distance = (float) $request->get('distance', 50); // Default 50km

            // Filter by radius
            $query->withinDistance($lat, $lng, $distance);

            // Order by computed distance (same haversine formula), alias as computed_distance
            $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';
            $query->select('*')
                ->addSelect(\DB::raw("{$haversine} as computed_distance"))
                ->addBinding([$lat, $lng, $lat], 'select')
                ->orderBy('computed_distance');
        }

        $monuments = $query->paginate(20);

        // Compute effective photo counts by dynamically including Commons category photos
        foreach ($monuments as $monument) {
            $effectiveCount = (int) ($monument->photo_count ?? 0);
            $effectiveHasPhotos = $monument->has_photos;

            if (! $monument->hasWikidataImage()) {
                $categoryUrl = $monument->commons_category_url ?? $monument->commons_url;
                if (! empty($categoryUrl)) {
                    try {
                        $existing = $monument->photos->pluck('commons_filename')->filter()->map(fn ($v) => strtolower((string) $v))->all();
                        $commonsPhotos = $commonsService->fetchPhotosByCategory($categoryUrl);
                        $added = 0;
                        foreach ($commonsPhotos as $cp) {
                            $fname = strtolower((string) ($cp['commons_filename'] ?? ''));
                            if ($fname === '' || in_array($fname, $existing, true)) {
                                continue;
                            }
                            $added++;
                        }
                        if ($added > 0) {
                            $effectiveCount += $added;
                            $effectiveHasPhotos = $effectiveHasPhotos || ($effectiveCount > 0);
                        }

                        // If no featured photo at all, use first Commons category photo as card preview
                        if (! $monument->featured_photo && ! empty($commonsPhotos)) {
                            $first = $commonsPhotos[0];
                            $monument->setAttribute('list_featured_photo', (object) [
                                'title' => $first['title'] ?? ($monument->name_tr ?? $monument->name_en),
                                'photographer' => $first['photographer'] ?? null,
                                'license_display_name' => $first['license_shortname'] ?? ($first['license'] ?? null),
                                'commons_url' => $first['commons_url'] ?? null,
                                'display_url' => $first['thumbnail_url'] ?? ($first['original_url'] ?? ($first['commons_url'] ?? null)),
                                'full_resolution_url' => $first['original_url'] ?? ($first['commons_url'] ?? null),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Soft fail; leave effective values as-is
                    }
                }
            }

            // Expose as transient attributes for the view
            $monument->setAttribute('effective_photo_count', $effectiveCount);
            $monument->setAttribute('effective_has_photos', (bool) $effectiveHasPhotos);
        }

        $categories = Category::active()->withMonuments()->get();
        $provinces = Monument::distinct()->pluck('province')->filter()->sort()->values();

        return view('monuments.list', compact('monuments', 'categories', 'provinces'));
    }

    /**
     * Display monument details.
     */
    public function show(string $identifier, \App\Services\WikidataSparqlService $sparqlService, \App\Services\WikimediaCommonsService $commonsService): View
    {
        $monument = $this->resolveMonument($identifier);
        $monument->load(['photos', 'categories']);

        // Build display photos: start with stored photos
        $displayPhotos = [];
        foreach ($monument->photos as $p) {
            $displayPhotos[] = [
                'id' => $p->id,
                'commons_filename' => $p->commons_filename,
                'title' => $p->title,
                'description' => $p->description,
                'photographer' => $p->photographer,
                'license_display_name' => $p->license_display_name,
                'license' => $p->license_display_name,
                'display_url' => $p->display_url,
                'full_resolution_url' => $p->full_resolution_url,
                'commons_url' => $p->commons_url,
            ];
        }

        // If Commons category exists, fetch and append category photos (dedupe by filename)
        $addedFromCommons = 0;
        $existingFilenames = collect($displayPhotos)->pluck('commons_filename')->filter()->map(fn ($v) => strtolower((string) $v))->all();
        if (! empty($monument->commons_url)) {
            try {
                $commonsPhotos = $commonsService->fetchPhotosByCategory($monument->commons_url);
                foreach ($commonsPhotos as $cp) {
                    $fname = strtolower((string) ($cp['commons_filename'] ?? ''));
                    if ($fname !== '' && in_array($fname, $existingFilenames, true)) {
                        continue;
                    }
                    $displayPhotos[] = [
                        'id' => null,
                        'commons_filename' => $cp['commons_filename'] ?? null,
                        'title' => $cp['title'] ?? $monument->primary_name,
                        'description' => $cp['description'] ?? null,
                        'photographer' => $cp['photographer'] ?? null,
                        'license_display_name' => $cp['license_shortname'] ?? ($cp['license'] ?? null),
                        'license' => $cp['license_shortname'] ?? ($cp['license'] ?? null),
                        'display_url' => $cp['thumbnail_url'] ?? $cp['original_url'] ?? ($cp['commons_url'] ?? null),
                        'full_resolution_url' => $cp['original_url'] ?? ($cp['commons_url'] ?? null),
                        'commons_url' => $cp['commons_url'] ?? null,
                    ];
                    $addedFromCommons++;
                    if ($fname !== '') {
                        $existingFilenames[] = $fname;
                    }
                }
            } catch (\Throwable $e) {
                // Soft fail; continue without Commons category
            }
        }

        $effectivePhotoCount = (int) $monument->photo_count + (int) $addedFromCommons;

        // Ensure location hierarchy is available for the detail page
        if (empty($monument->location_hierarchy_tr) && ! empty($monument->wikidata_id)) {
            try {
                $computed = $sparqlService->fetchLocationHierarchyString($monument->wikidata_id);
                if (! empty($computed)) {
                    // Set for this response; persistence handled by backfill/cron
                    $monument->setAttribute('location_hierarchy_tr', $computed);
                }
            } catch (\Throwable $e) {
                // Soft-fail; show fallback in view
            }
        }

        return view('monuments.show', [
            'monument' => $monument,
            'displayPhotos' => $displayPhotos,
            'effectivePhotoCount' => $effectivePhotoCount,
        ]);
    }

    /**
     * API: Get map markers (thin payload) with optional filters.
     */
    public function apiMapMarkers(Request $request): JsonResponse
    {
        $zoom = (int) $request->get('zoom', 0);
        $coverage = (string) $request->get('coverage', '');
        $b = $request->get('bounds');

        $query = Monument::query()
            ->select(['id', 'wikidata_id', 'name_tr', 'name_en', 'latitude', 'longitude', 'province', 'city', 'location_hierarchy_tr', 'has_photos', 'photo_count', 'properties'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply bounds: either explicit bounds from request or full Turkey coverage when requested
        $applyBounds = false;
        if ($coverage === 'turkey') {
            // Approximate bounding box for Turkey (includes entire mainland and main territories)
            $b = [
                'south' => 35.8,
                'west' => 25.9,
                'north' => 42.3,
                'east' => 45.0,
            ];
            $applyBounds = true;
        } elseif ($request->filled('bounds')) {
            $applyBounds = true;
        }

        if ($applyBounds) {
            $query->whereBetween('latitude', [$b['south'], $b['north']])
                ->whereBetween('longitude', [$b['west'], $b['east']]);
        }

        // Optional filters from UI
        if ($request->filled('q')) {
            $query->search($request->string('q'));
        }
        if ($request->filled('province')) {
            $query->byProvince($request->get('province'));
        }
        if ($request->filled('category')) {
            $categoryId = $request->get('category');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }
        if ($request->has('has_photos')) {
            if ($request->get('has_photos') === '1') {
                $query->withPhotos();
            } else {
                $query->withoutPhotos();
            }
        }

        // Cache key and TTL
        $cacheKey = null;
        $ttlSeconds = $coverage === 'turkey' ? 600 : 60; // Response caching header only; data is pre-warmed
        $filtersHash = md5(json_encode([
            'q' => $request->get('q'),
            'province' => $request->get('province'),
            'category' => $request->get('category'),
            'has_photos' => $request->get('has_photos'),
        ]));

        if ($coverage === 'turkey') {
            $cacheKey = 'markers:coverage:turkey:'.$filtersHash;
        } elseif ($request->filled('bounds')) {
            // Cache key: zoom + quantized bounds + filters hash
            $precision = $zoom >= 12 ? 3 : ($zoom >= 9 ? 2 : 1);
            $q = function ($v) use ($precision) {
                return round((float) $v, $precision);
            };
            $cacheKey = 'markers:'.implode(':', ['z'.$zoom, $q($b['south']), $q($b['west']), $q($b['north']), $q($b['east']), $filtersHash]);
        }

        $compute = function () use ($query) {
            return $query->get()->map(function ($m) {
                // Build structured featured photo including author/license when available
                $featured = null;
                $fp = $m->featured_photo;
                if ($fp) {
                    if (is_object($fp)) {
                        $featured = [
                            'title' => $fp->title ?? ($m->name_tr ?? $m->name_en),
                            'photographer' => $fp->photographer ?? null,
                            'license' => $fp->license_display_name ?? ($fp->license ?? null),
                            'commons_url' => $fp->commons_url ?? null,
                            'display_url' => $fp->display_url ?? $fp->full_resolution_url ?? null,
                            'full_resolution_url' => $fp->full_resolution_url ?? $fp->display_url ?? null,
                        ];
                    } else {
                        // String URL fallback just in case
                        $featured = [
                            'title' => $m->name_tr ?? $m->name_en,
                            'photographer' => null,
                            'license' => null,
                            'commons_url' => null,
                            'display_url' => (string) $fp,
                            'full_resolution_url' => (string) $fp,
                        ];
                    }
                }

                // Minimal type/category metadata for client-side filtering
                $props = is_array($m->properties) ? $m->properties : (is_string($m->properties) ? (json_decode($m->properties, true) ?: []) : []);
                $typeQid = $props['instance_of'] ?? null;
                $typeLabelTr = $props['instance_of_label_tr'] ?? null;
                $categoryQid = $typeQid;
                $categoryLabelTr = $typeLabelTr;

                return [
                    'id' => $m->id,
                    'wikidata_id' => $m->wikidata_id,
                    'name' => $m->name_tr ?? $m->name_en ?? 'Unnamed Monument',
                    'description' => $m->description_tr ?? $m->description_en,
                    'coordinates' => [
                        'lat' => (float) $m->latitude,
                        'lng' => (float) $m->longitude,
                    ],
                    'province' => $m->province,
                    'has_photos' => (bool) $m->has_photos,
                    'photo_count' => (int) $m->photo_count,
                    // Category fields for client filtering
                    'category_qid' => $categoryQid,
                    'category_label_tr' => $categoryLabelTr,
                    'type_qid' => $typeQid,
                    'type_label_tr' => $typeLabelTr,
                    'featured_photo' => $featured,
                    'url' => "/monuments/{$m->id}",
                ];
            });
        };

        if ($coverage === 'turkey' && $cacheKey) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached)
                    ->header('Cache-Control', 'public, max-age='.$ttlSeconds)
                    ->header('X-Cache', 'HIT');
            }
            // Determine if filtered request; if filtered, compute on miss to avoid empty results
            $isFiltered = $request->filled('q') || $request->filled('province') || $request->filled('category') || $request->has('has_photos');
            if ($isFiltered) {
                $markers = $compute();
                Cache::put($cacheKey, $markers, now()->addMinutes(10));

                return response()->json($markers)
                    ->header('Cache-Control', 'public, max-age='.$ttlSeconds)
                    ->header('X-Cache', 'MISS-COMPUTED');
            }
            // Unfiltered: dispatch warm job and return fast, empty set
            \App\Jobs\WarmTurkeyMarkersJob::dispatch();

            return response()->json([])
                ->header('Cache-Control', 'no-cache')
                ->header('X-Cache', 'MISS');
        }

        $markers = $cacheKey ? Cache::remember($cacheKey, $ttlSeconds, $compute) : $compute();

        return response()->json($markers)->header('Cache-Control', 'public, max-age='.$ttlSeconds);
    }

    /**
     * API: Search monuments.
     */
    public function apiSearch(Request $request): JsonResponse
    {
        $query = Monument::with(['photos', 'categories']);

        // Apply search filters
        if ($request->filled('q')) {
            $query->search($request->string('q'));
        }

        // Apply filters
        if ($request->filled('province')) {
            $query->byProvince($request->get('province'));
        }

        if ($request->filled('city')) {
            $query->byCity($request->get('city'));
        }

        if ($request->filled('heritage_status')) {
            $query->byHeritageStatus($request->get('heritage_status'));
        }

        if ($request->filled('has_photos')) {
            if ($request->get('has_photos') === '1') {
                $query->withPhotos();
            } else {
                $query->withoutPhotos();
            }
        }

        if ($request->filled('category')) {
            $categoryId = $request->get('category');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        // Distance-based filtering
        if ($request->filled('lat') && $request->filled('lng') && $request->filled('distance')) {
            $lat = $request->get('lat');
            $lng = $request->get('lng');
            $distance = $request->get('distance', 50);
            $query->withinDistance($lat, $lng, $distance);
        }

        $monuments = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $monuments->items(),
            'pagination' => [
                'current_page' => $monuments->currentPage(),
                'last_page' => $monuments->lastPage(),
                'per_page' => $monuments->perPage(),
                'total' => $monuments->total(),
            ],
        ]);
    }

    /**
     * API: Get monument details.
     */
    public function apiShow(string $identifier, \App\Services\WikimediaCommonsService $commonsService): JsonResponse
    {
        $monument = $this->resolveMonument($identifier);
        $monument->load(['photos', 'categories']);

        // Prepare combined photos including Commons category if available
        $photosOut = [];
        foreach ($monument->photos as $photo) {
            $photosOut[] = [
                'id' => $photo->id,
                'commons_filename' => $photo->commons_filename,
                'title' => $photo->title,
                'description' => $photo->description,
                'photographer' => $photo->photographer,
                'license' => $photo->license_display_name,
                'date_taken' => $photo->formatted_date_taken,
                'display_url' => $photo->display_url,
                'full_resolution_url' => $photo->full_resolution_url,
                'is_featured' => $photo->is_featured,
                'is_uploaded_via_app' => $photo->is_uploaded_via_app,
                'commons_url' => $photo->commons_url,
            ];
        }

        $addedFromCommons = 0;
        $hasWikidataImage = $monument->hasWikidataImage();
        $existingFilenames = collect($photosOut)->pluck('commons_filename')->filter()->map(fn ($v) => strtolower((string) $v))->all();
        // Only enrich with Commons category photos for the map panel if Wikidata image is absent
        if (! $hasWikidataImage && ! empty($monument->commons_url)) {
            try {
                $commonsPhotos = $commonsService->fetchPhotosByCategory($monument->commons_url);
                foreach ($commonsPhotos as $cp) {
                    $fname = strtolower((string) ($cp['commons_filename'] ?? ''));
                    if ($fname !== '' && in_array($fname, $existingFilenames, true)) {
                        continue;
                    }
                    $photosOut[] = [
                        'id' => null,
                        'commons_filename' => $cp['commons_filename'] ?? null,
                        'title' => $cp['title'] ?? $monument->primary_name,
                        'description' => $cp['description'] ?? null,
                        'photographer' => $cp['photographer'] ?? null,
                        'license' => $cp['license_shortname'] ?? ($cp['license'] ?? null),
                        'date_taken' => null,
                        'display_url' => $cp['thumbnail_url'] ?? $cp['original_url'] ?? ($cp['commons_url'] ?? null),
                        'full_resolution_url' => $cp['original_url'] ?? ($cp['commons_url'] ?? null),
                        'is_featured' => false,
                        'is_uploaded_via_app' => false,
                        'commons_url' => $cp['commons_url'] ?? null,
                    ];
                    $addedFromCommons++;
                    if ($fname !== '') {
                        $existingFilenames[] = $fname;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore Commons fetch errors in API response
            }
        }

        // Optional: include raw Wikidata entity when requested
        $raw = request()->boolean('raw');
        $rawEntity = null;
        if ($raw && $monument->wikidata_id) {
            $rawEntity = \App\Services\WikidataSparqlService::getEntityData($monument->wikidata_id);
        }

        return response()->json([
            'id' => $monument->id,
            'wikidata_id' => $monument->wikidata_id,
            'name' => $monument->primary_name,
            'description' => $monument->primary_description,
            'coordinates' => $monument->coordinates,
            'commons_category' => $monument->commons_category,
            'address' => null,
            'city' => null,
            'province' => $monument->province,
            'admin_area' => $monument->admin_area,
            'location_hierarchy_tr' => $monument->location_hierarchy_tr,
            'heritage_status' => null,
            'construction_date' => null,
            'architect' => null,
            'style' => null,
            'material' => null,
            'wikidata_url' => $monument->wikidata_url,
            'wikipedia_url' => $monument->wikipedia_url,
            'commons_url' => $monument->commons_url,
            'has_photos' => $monument->has_photos || (! $hasWikidataImage && $addedFromCommons > 0),
            'photo_count' => (int) $monument->photo_count + (! $hasWikidataImage ? (int) $addedFromCommons : 0),
            'photos' => $photosOut,
            'categories' => $monument->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->primary_name,
                    'description' => $category->primary_description,
                    'color' => $category->color,
                ];
            }),
            'last_synced_at' => $monument->last_synced_at?->toISOString(),
            'raw_wikidata' => $rawEntity,
        ]);
    }

    /**
     * API: Get available filters.
     */
    public function apiFilters(): JsonResponse
    {
        try {
            // Get provinces from database first, then supplement with Wikidata if needed
            $dbProvinces = Monument::distinct()->pluck('province')->filter()->sort()->values();

            // If we have less than 20 provinces, fetch from Wikidata
            if ($dbProvinces->count() < 20) {
                $sparqlService = new \App\Services\WikidataSparqlService;
                $wikidataProvinces = $sparqlService->fetchTurkishProvinces();

                // Merge and deduplicate
                $allProvinces = $dbProvinces->merge($wikidataProvinces)->unique()->sort()->values();
            } else {
                $allProvinces = $dbProvinces;
            }

            $cities = Monument::distinct()->pluck('city')->filter()->sort()->values();
            $heritageStatuses = Monument::distinct()->pluck('heritage_status')->filter()->sort()->values();

            // Get categories from database
            $categories = Category::active()->withMonuments()->get()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->primary_name,
                    'wikidata_id' => $category->wikidata_id,
                    'monument_count' => $category->monument_count,
                ];
            });

            return response()->json([
                'provinces' => $allProvinces,
                'cities' => $cities,
                'heritage_statuses' => $heritageStatuses,
                'categories' => $categories,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'provinces' => [],
                'cities' => [],
                'heritage_statuses' => [],
                'categories' => [],
                'warning' => 'Filters unavailable',
            ]);
        }
    }

    /**
     * API: Get Wikidata label for a Q-code.
     */
    public function apiWikidataLabel(string $qcode): JsonResponse
    {
        try {
            $label = \App\Services\WikidataSparqlService::getLabelForQCode($qcode);

            return response()->json(['label' => $label]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch label'], 500);
        }
    }

    /**
     * API: Warm cache for Turkey-wide markers.
     */
    public function apiWarmTurkeyMarkers(Request $request): JsonResponse
    {
        $provided = (string) ($request->header('X-Cache-Warm-Token') ?? $request->get('token', ''));
        $expected = (string) config('services.cache_warm.token');

        if ($expected === '' || hash_equals($expected, $provided) === false) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        \App\Jobs\WarmTurkeyMarkersJob::dispatch();

        return response()->json(['success' => true, 'queued' => true]);
    }

    /**
     * API: Test image display from Wikimedia Commons without storing locally.
     */
    public function apiTestImages(Request $request): JsonResponse
    {
        try {
            $sparqlService = new \App\Services\WikidataSparqlService;
            $monumentsWithImages = $sparqlService->fetchMonumentsWithImages();

            return response()->json([
                'success' => true,
                'count' => count($monumentsWithImages),
                'monuments' => $monumentsWithImages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch monuments with images: '.$e->getMessage(),
            ], 500);
        }
    }
}
