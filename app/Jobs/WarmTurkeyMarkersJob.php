<?php

namespace App\Jobs;

use App\Models\Monument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WarmTurkeyMarkersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $variants = [
            [],
            ['has_photos' => '0'],
        ];

        foreach ($variants as $params) {
            $filtersHash = md5(json_encode([
                'q' => $params['q'] ?? null,
                'province' => $params['province'] ?? null,
                'category' => $params['category'] ?? null,
                'has_photos' => $params['has_photos'] ?? null,
            ]));
            $cacheKey = 'markers:coverage:turkey:'.$filtersHash;

            $query = Monument::query()
                ->select(['id', 'wikidata_id', 'name_tr', 'name_en', 'description_tr', 'description_en', 'latitude', 'longitude', 'province', 'city', 'location_hierarchy_tr', 'has_photos', 'photo_count', 'properties'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [35.8, 42.3])
                ->whereBetween('longitude', [25.9, 45.0]);

            if (array_key_exists('q', $params) && $params['q']) {
                $query->search($params['q']);
            }
            if (array_key_exists('province', $params) && $params['province']) {
                $query->byProvince($params['province']);
            }
            if (array_key_exists('category', $params) && $params['category']) {
                $categoryId = $params['category'];
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }
            if (array_key_exists('has_photos', $params)) {
                if ($params['has_photos'] === '1') {
                    $query->withPhotos();
                } else {
                    $query->withoutPhotos();
                }
            }

            $markers = [];
            (clone $query)
                ->with(['photos' => fn ($q) => $q->orderByRaw('is_featured DESC')->orderBy('id')->limit(1)])
                ->chunk(2000, function ($monuments) use (&$markers) {
                    foreach ($monuments as $m) {
                        $markers[] = $m->toMapMarkerPayload($m->photos->first());
                    }
                });

            Cache::put($cacheKey, $markers, now()->addMinutes(15));
        }
    }
}
