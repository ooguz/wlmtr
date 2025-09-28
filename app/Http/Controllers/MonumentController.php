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
    public function list(Request $request): View
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
            $categoryId = $request->get('category');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        // Distance-based filtering
        if ($request->filled('lat') && $request->filled('lng') && $request->filled('distance')) {
            $lat = $request->get('lat');
            $lng = $request->get('lng');
            $distance = $request->get('distance', 50); // Default 50km
            $query->withinDistance($lat, $lng, $distance);
        }

        $monuments = $query->paginate(20);

        $categories = Category::active()->withMonuments()->get();
        $provinces = Monument::distinct()->pluck('province')->filter()->sort()->values();

        return view('monuments.list', compact('monuments', 'categories', 'provinces'));
    }

    /**
     * Display monument details.
     */
    public function show(Monument $monument, \App\Services\WikidataSparqlService $sparqlService): View
    {
        $monument->load(['photos', 'categories']);

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

        return view('monuments.show', compact('monument'));
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
        $ttlSeconds = $coverage === 'turkey' ? 600 : 60; // Longer cache for full-country initial load
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
                    'city' => $m->city,
                    'location_hierarchy_tr' => $m->location_hierarchy_tr,
                    'has_photos' => (bool) $m->has_photos,
                    'photo_count' => (int) $m->photo_count,
                    'featured_photo' => $featured,
                    'url' => "/monuments/{$m->id}",
                ];
            });
        };

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
    public function apiShow(Monument $monument): JsonResponse
    {
        $monument->load(['photos', 'categories']);

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
            'address' => $monument->address,
            'city' => $monument->city,
            'province' => $monument->province,
            'admin_area' => $monument->admin_area,
            'location_hierarchy_tr' => $monument->location_hierarchy_tr,
            'heritage_status' => $monument->heritage_status,
            'construction_date' => $monument->construction_date,
            'architect' => $monument->architect,
            'style' => $monument->style,
            'material' => $monument->material,
            'wikidata_url' => $monument->wikidata_url,
            'wikipedia_url' => $monument->wikipedia_url,
            'commons_url' => $monument->commons_url,
            'has_photos' => $monument->has_photos,
            'photo_count' => $monument->photo_count,
            'photos' => $monument->photos->map(function ($photo) {
                return [
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
                ];
            }),
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
