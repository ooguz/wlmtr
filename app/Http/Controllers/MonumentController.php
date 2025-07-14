<?php

namespace App\Http\Controllers;

use App\Models\Monument;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_tr', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('description_tr', 'like', "%{$search}%")
                  ->orWhere('description_en', 'like', "%{$search}%");
            });
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
    public function show(Monument $monument): View
    {
        $monument->load(['photos', 'categories']);
        
        return view('monuments.show', compact('monument'));
    }

    /**
     * API: Get monuments for map markers.
     */
    public function apiMapMarkers(Request $request): JsonResponse
    {
        $query = Monument::with(['photos', 'categories'])
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

        $monuments = $query->get()->map(function ($monument) {
            return [
                'id' => $monument->id,
                'wikidata_id' => $monument->wikidata_id,
                'name' => $monument->primary_name,
                'description' => $monument->primary_description,
                'coordinates' => $monument->coordinates,
                'has_photos' => $monument->has_photos,
                'photo_count' => $monument->photo_count,
                'featured_photo' => $monument->featured_photo?->display_url,
                'photos' => $monument->photos->take(5)->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'title' => $photo->title,
                        'description' => $photo->description,
                        'photographer' => $photo->photographer,
                        'license' => $photo->license_display_name,
                        'display_url' => $photo->display_url,
                        'full_resolution_url' => $photo->full_resolution_url,
                        'commons_url' => $photo->commons_url,
                        'is_featured' => $photo->is_featured,
                    ];
                }),
                'categories' => $monument->categories->pluck('primary_name'),
                'heritage_status' => $monument->heritage_status,
                'province' => $monument->province,
                'city' => $monument->city,
                'url' => route('monuments.show', $monument),
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
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('name_tr', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('description_tr', 'like', "%{$search}%")
                  ->orWhere('description_en', 'like', "%{$search}%");
            });
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

        return response()->json([
            'id' => $monument->id,
            'wikidata_id' => $monument->wikidata_id,
            'name' => $monument->primary_name,
            'description' => $monument->primary_description,
            'coordinates' => $monument->coordinates,
            'address' => $monument->address,
            'city' => $monument->city,
            'province' => $monument->province,
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
        ]);
    }

    /**
     * API: Get available filters.
     */
    public function apiFilters(): JsonResponse
    {
        $provinces = Monument::distinct()->pluck('province')->filter()->sort()->values();
        $cities = Monument::distinct()->pluck('city')->filter()->sort()->values();
        $heritageStatuses = Monument::distinct()->pluck('heritage_status')->filter()->sort()->values();
        $categories = Category::active()->withMonuments()->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->primary_name,
                'monument_count' => $category->monument_count,
            ];
        });

        return response()->json([
            'provinces' => $provinces,
            'cities' => $cities,
            'heritage_statuses' => $heritageStatuses,
            'categories' => $categories,
        ]);
    }
}
