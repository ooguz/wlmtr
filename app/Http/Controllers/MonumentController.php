<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Monument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonumentController extends Controller
{
    /**
     * Display the monuments map view.
     */
    public function map(Request $request): View
    {
        $monuments = Monument::with(['photos', 'categories'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        return view('monuments.map', compact('monuments'));
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
     * API: Get monuments for map markers.
     */
    public function apiMapMarkers(Request $request): JsonResponse
    {
        $query = Monument::query()
            ->select(['id', 'wikidata_id', 'name_tr', 'name_en', 'description_tr', 'description_en', 'latitude', 'longitude', 'has_photos', 'photo_count', 'province', 'city', 'district', 'location_hierarchy_tr'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply filters
        if ($request->filled('bounds')) {
            $bounds = $request->get('bounds');
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        if ($request->filled('has_photos')) {
            if ($request->get('has_photos') === '1') {
                $query->withPhotos();
            } else {
                $query->withoutPhotos();
            }
        }

        // Safety cap if bounds not provided
        if (! $request->filled('bounds')) {
            $query->orderByDesc('last_synced_at')->limit(2000);
        }

        $monuments = $query->get()->map(function ($monument) {
            return [
                'id' => $monument->id,
                'wikidata_id' => $monument->wikidata_id,
                'name' => $monument->name_tr ?? $monument->name_en ?? 'Unnamed Monument',
                'description' => $monument->description_tr ?? $monument->description_en,
                'coordinates' => [
                    'lat' => (float) $monument->latitude,
                    'lng' => (float) $monument->longitude,
                ],
                'has_photos' => (bool) $monument->has_photos,
                'photo_count' => (int) $monument->photo_count,
                'featured_photo' => null, // Skip featured photo for performance
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
                        'commons_url' => $photo->commons_url,
                        'is_featured' => $photo->is_featured,
                        'is_uploaded_via_app' => $photo->is_uploaded_via_app,
                    ];
                }),
                'province' => $monument->province,
                'city' => $monument->city,
                'district' => $monument->district,
                'country' => $monument->country,
                'admin_area' => $monument->location_hierarchy_tr,
                'location_hierarchy_tr' => $monument->location_hierarchy_tr,
                'categories' => $monument->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->primary_name,
                    ];
                }),
                'url' => "/monuments/{$monument->id}",
            ];
        });

        return response()->json($monuments);
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
                $sparqlService = new \App\Services\WikidataSparqlService();
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
     * API: Test image display from Wikimedia Commons without storing locally.
     */
    public function apiTestImages(Request $request): JsonResponse
    {
        try {
            $sparqlService = new \App\Services\WikidataSparqlService();
            $monumentsWithImages = $sparqlService->fetchMonumentsWithImages();

            return response()->json([
                'success' => true,
                'count' => count($monumentsWithImages),
                'monuments' => $monumentsWithImages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch monuments with images: ' . $e->getMessage(),
            ], 500);
        }
    }
}
