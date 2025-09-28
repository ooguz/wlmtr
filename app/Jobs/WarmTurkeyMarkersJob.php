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
                ->select(['id', 'wikidata_id', 'name_tr', 'name_en', 'latitude', 'longitude', 'province', 'city', 'location_hierarchy_tr', 'has_photos', 'photo_count', 'properties'])
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

            $compute = function () use ($query) {
                return $query->get()->map(function ($m) {
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

            // Warm with a slightly longer TTL to bridge refresh gaps
            Cache::put($cacheKey, $compute(), now()->addMinutes(15));
        }
    }
}


