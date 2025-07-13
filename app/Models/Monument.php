<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Monument extends Model
{
    protected $fillable = [
        'wikidata_id',
        'name_tr',
        'name_en',
        'description_tr',
        'description_en',
        'latitude',
        'longitude',
        'address',
        'city',
        'district',
        'province',
        'country',
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
     * Check if monument has coordinates.
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinatesAttribute(): ?array
    {
        if (!$this->hasCoordinates()) {
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
        return $this->photos()->where('is_featured', true)->first() 
               ?? $this->photos()->first();
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
     * Get the distance from given coordinates in kilometers.
     */
    public function getDistanceFrom($lat, $lng): ?float
    {
        if (!$this->hasCoordinates()) {
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
}
