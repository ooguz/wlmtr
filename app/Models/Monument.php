<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monument extends Model
{
    protected $fillable = [
        'wikidata_id',
        'name_tr',
        'name_en',
        'description_tr',
        'description_en',
        'aka',
        'kulturenvanteri_id',
        'commons_category',
        'latitude',
        'longitude',
        'address',
        'city',
        'district',
        'province',
        'country',
        'location_hierarchy_tr',
        'heritage_status',
        'construction_date',
        'architect',
        'style',
        'material',
        'wikidata_url',
        'wikipedia_url',
        'commons_url',
        'has_photos',
        'photo_count',
        'properties',
        'last_synced_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'has_photos' => 'boolean',
        'photo_count' => 'integer',
        'properties' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the photos for this monument.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Get the categories for this monument.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_monument');
    }

    /**
     * Get the primary name (Turkish preferred, fallback to English).
     */
    public function getPrimaryNameAttribute(): string
    {
        return $this->name_tr ?? $this->name_en ?? 'Unnamed Monument';
    }

    /**
     * Get the primary description (Turkish preferred, fallback to English).
     */
    public function getPrimaryDescriptionAttribute(): ?string
    {
        return $this->description_tr ?? $this->description_en;
    }

    /**
     * Determine if Wikidata provides a primary image (P18) for this monument.
     */
    public function hasWikidataImage(): bool
    {
        $props = $this->properties;
        if (is_string($props)) {
            $decoded = json_decode($props, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $props = $decoded;
            } else {
                $props = null;
            }
        }

        if (is_array($props) && ! empty($props['image'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if monument has coordinates.
     */
    public function hasCoordinates(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinatesAttribute(): ?array
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }

    /**
     * Get the featured photo for this monument.
     */
    public function getFeaturedPhotoAttribute()
    {
        // Prefer synced photo if exists
        $photo = $this->photos()->where('is_featured', true)->first() ?? $this->photos()->first();
        if ($photo) {
            return $photo;
        }
        // Fallback to Commons image stored in properties
        $image = $this->properties['image'] ?? null;
        if ($image) {
            return (object) [
                'title' => $this->primary_name,
                'display_url' => static::buildCommonsThumbnailUrl($image, 640),
                'full_resolution_url' => static::buildCommonsImageUrl($image),
                'commons_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode($image),
                'license_display_name' => null,
            ];
        }

        return null;
    }

    public static function buildCommonsThumbnailUrl(string $fileTitle, int $width = 640): string
    {
        $title = str_replace(' ', '_', preg_replace('/^File:/i', '', $fileTitle));
        $encoded = rawurlencode($title);

        // Use Special:Redirect to avoid hashing logic
        return "https://commons.wikimedia.org/w/index.php?title=Special:Redirect/file/{$encoded}&width={$width}";
    }

    public static function buildCommonsImageUrl(string $fileTitle): string
    {
        $title = str_replace(' ', '_', preg_replace('/^File:/i', '', $fileTitle));
        $encoded = rawurlencode($title);

        return "https://commons.wikimedia.org/wiki/File:{$encoded}";
    }

    /**
     * Build map marker payload (use with eager-loaded photos to avoid N+1).
     *
     * @param  object|\App\Models\Photo|null  $featuredPhoto  Pre-loaded photo, or null to use featured_photo accessor
     */
    public function toMapMarkerPayload(?object $featuredPhoto = null): array
    {
        $fp = $featuredPhoto ?? $this->featured_photo;
        $featured = null;
        if ($fp) {
            if (is_object($fp) && isset($fp->commons_url)) {
                $featured = [
                    'title' => $fp->title ?? ($this->name_tr ?? $this->name_en),
                    'photographer' => $fp->photographer ?? null,
                    'license' => $fp->license_display_name ?? ($fp->license ?? null),
                    'commons_url' => $fp->commons_url ?? null,
                    'display_url' => $fp->display_url ?? $fp->full_resolution_url ?? null,
                    'full_resolution_url' => $fp->full_resolution_url ?? $fp->display_url ?? null,
                ];
            } elseif (is_object($fp) && (isset($fp->display_url) || isset($fp->full_resolution_url))) {
                $featured = [
                    'title' => $this->name_tr ?? $this->name_en,
                    'photographer' => null,
                    'license' => null,
                    'commons_url' => $fp->commons_url ?? null,
                    'display_url' => (string) ($fp->display_url ?? $fp->full_resolution_url ?? ''),
                    'full_resolution_url' => (string) ($fp->full_resolution_url ?? $fp->display_url ?? ''),
                ];
            }
        } elseif (isset($this->properties['image'])) {
            $image = $this->properties['image'];
            $featured = [
                'title' => $this->name_tr ?? $this->name_en,
                'photographer' => null,
                'license' => null,
                'commons_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode($image),
                'display_url' => static::buildCommonsThumbnailUrl($image, 640),
                'full_resolution_url' => static::buildCommonsImageUrl($image),
            ];
        }

        $props = is_array($this->properties) ? $this->properties : (is_string($this->properties) ? (json_decode($this->properties, true) ?: []) : []);
        $typeQid = $props['instance_of'] ?? null;
        $typeLabelTr = $props['instance_of_label_tr'] ?? null;

        return [
            'id' => $this->id,
            'wikidata_id' => $this->wikidata_id,
            'name' => $this->name_tr ?? $this->name_en ?? 'Unnamed Monument',
            'description' => $this->description_tr ?? $this->description_en,
            'coordinates' => [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ],
            'province' => $this->province,
            'city' => $this->city,
            'location_hierarchy_tr' => $this->location_hierarchy_tr,
            'has_photos' => (bool) $this->has_photos,
            'photo_count' => (int) $this->photo_count,
            'category_qid' => $typeQid,
            'category_label_tr' => $typeLabelTr,
            'type_qid' => $typeQid,
            'type_label_tr' => $typeLabelTr,
            'featured_photo' => $featured,
            'url' => "/monuments/{$this->id}",
        ];
    }

    /**
     * Get Wikipedia Turkish URL.
     */
    public function getWikipediaTrUrlAttribute(): ?string
    {
        if (! $this->wikidata_id) {
            return null;
        }

        // Try to get the Turkish Wikipedia page from Wikidata properties
        if (isset($this->properties['sitelinks']['trwiki']['url'])) {
            return $this->properties['sitelinks']['trwiki']['url'];
        }

        // Fallback: construct URL using Turkish name
        if ($this->name_tr) {
            $title = str_replace(' ', '_', $this->name_tr);

            return "https://tr.wikipedia.org/wiki/{$title}";
        }

        return null;
    }

    public function getAdminAreaTrAttribute(): ?string
    {
        if (! empty($this->location_hierarchy_tr)) {
            return $this->location_hierarchy_tr;
        }

        $props = $this->properties;
        if (is_string($props)) {
            $decoded = json_decode($props, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $props = $decoded;
            } else {
                $props = null;
            }
        }
        if (is_array($props) && ! empty($props['admin_label_tr'])) {
            return $props['admin_label_tr'];
        }

        // Live fallback: resolve P131 chain from Wikidata if we have a Q-code
        if (! empty($this->wikidata_id)) {
            try {
                $service = new \App\Services\WikidataSparqlService;
                $hierarchy = $service->fetchLocationHierarchyString($this->wikidata_id);
                if (! empty($hierarchy)) {
                    // Cache the result for this request
                    $this->setAttribute('location_hierarchy_tr', $hierarchy);

                    return $hierarchy;
                }
            } catch (\Throwable $e) {
                // Ignore and fall through to null
            }
        }

        return null;
    }

    /**
     * Get Wikipedia English URL.
     */
    public function getWikipediaEnUrlAttribute(): ?string
    {
        if (! $this->wikidata_id) {
            return null;
        }

        // Try to get the English Wikipedia page from Wikidata properties
        if (isset($this->properties['sitelinks']['enwiki']['url'])) {
            return $this->properties['sitelinks']['enwiki']['url'];
        }

        // Fallback: construct URL using English name
        if ($this->name_en) {
            $title = str_replace(' ', '_', $this->name_en);

            return "https://en.wikipedia.org/wiki/{$title}";
        }

        return null;
    }

    /**
     * Get Commons category URL from commons_category field.
     */
    public function getCommonsCategoryUrlAttribute(): ?string
    {
        if (! $this->commons_category) {
            return null;
        }

        return "https://commons.wikimedia.org/wiki/Category:{$this->commons_category}";
    }

    /**
     * Scope to filter monuments with photos.
     */
    public function scopeWithPhotos($query)
    {
        return $query->where('has_photos', true);
    }

    /**
     * Scope to filter monuments without photos.
     */
    public function scopeWithoutPhotos($query)
    {
        return $query->where('has_photos', false);
    }

    /**
     * Scope to filter monuments by province.
     */
    public function scopeByProvince($query, $province)
    {
        return $query->where('province', $province);
    }

    /**
     * Scope to filter monuments by city.
     */
    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope to filter monuments by heritage status.
     */
    public function scopeByHeritageStatus($query, $status)
    {
        return $query->where('heritage_status', $status);
    }

    /**
     * Scope to filter monuments within a certain distance from coordinates.
     */
    public function scopeWithinDistance($query, $lat, $lng, $distanceKm)
    {
        return $query->whereRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
            [$lat, $lng, $lat, $distanceKm]
        );
    }

    /**
     * Scope: full-text search across name and description fields.
     */
    public function scopeSearch($query, string $term)
    {
        $connection = $query->getModel()->getConnection();
        $driverName = $connection->getDriverName();

        $columns = ['name_tr', 'name_en', 'description_tr', 'description_en'];

        if ($driverName === 'mysql') {
            return $query->whereFullText($columns, $term);
        }

        // Fallback for SQLite or others
        return $query->where(function ($q) use ($columns, $term) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', "%{$term}%");
            }
        });
    }

    /**
     * Get the distance from given coordinates in kilometers.
     */
    public function getDistanceFrom($lat, $lng): ?float
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($lat);
        $lonFrom = deg2rad($lng);
        $latTo = deg2rad($this->latitude);
        $lonTo = deg2rad($this->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    /**
     * Get Wikimedia Commons Upload Wizard URL for this monument.
     */
    public function getUploadWizardUrlAttribute(): string
    {
        $baseUrl = 'https://commons.wikimedia.org/wiki/Special:UploadWizard';

        // Build description with monument name and WLM template
        $description = $this->primary_name;
        if ($this->wikidata_id) {
            $description .= "\n{{on Wikidata|{$this->wikidata_id}}}";
            $description .= "\n{{Load via app WLM.tr|year=".date('Y').'|source=wizard}}';
        }

        $params = [
            'description' => $description,
            'descriptionlang' => 'tr',
            'campaign' => 'wlm-tr',
        ];

        // Add categories
        $categories = '';
        if (is_null($this->province) && $this->location_hierarchy_tr) {
            $categories = explode(',', $this->location_hierarchy_tr)[0];
        } elseif ($this->province) {
            $categories = $this->province;
        }

        if ($this->commons_category) {
            $categories .= ($categories ? '|' : '').$this->commons_category;
        }

        if ($categories) {
            $params['categories'] = $categories;
        }

        // Add coordinates
        if ($this->hasCoordinates()) {
            $params['lat'] = (float) $this->latitude;
            $params['lon'] = (float) $this->longitude;
        }

        // Add monument ID if available
        if ($this->wikidata_id) {
            $params['id'] = $this->wikidata_id;
        }

        return $baseUrl.'?'.http_build_query($params);
    }
}
