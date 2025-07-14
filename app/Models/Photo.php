<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Photo extends Model
{
    protected $fillable = [
        'monument_id',
        'user_id',
        'commons_filename',
        'commons_url',
        'thumbnail_url',
        'original_url',
        'title',
        'description',
        'photographer',
        'license',
        'license_shortname',
        'date_taken',
        'camera_model',
        'exif_data',
        'is_featured',
        'is_uploaded_via_app',
        'uploaded_at',
    ];

    protected $casts = [
        'date_taken' => 'date',
        'exif_data' => 'array',
        'is_featured' => 'boolean',
        'is_uploaded_via_app' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the monument that owns this photo.
     */
    public function monument(): BelongsTo
    {
        return $this->belongsTo(Monument::class);
    }

    /**
     * Get the user who uploaded this photo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter photos uploaded via the app.
     */
    public function scopeUploadedViaApp($query)
    {
        return $query->where('is_uploaded_via_app', true);
    }

    /**
     * Scope to filter featured photos.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter photos by photographer.
     */
    public function scopeByPhotographer($query, $photographer)
    {
        return $query->where('photographer', $photographer);
    }

    /**
     * Get the display URL (thumbnail preferred, fallback to original).
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->thumbnail_url ?? $this->original_url ?? $this->commons_url;
    }

    /**
     * Get the full resolution URL.
     */
    public function getFullResolutionUrlAttribute(): string
    {
        return $this->original_url ?? $this->commons_url;
    }

    /**
     * Check if photo has EXIF data.
     */
    public function hasExifData(): bool
    {
        return !empty($this->exif_data);
    }

    /**
     * Get formatted date taken.
     */
    public function getFormattedDateTakenAttribute(): ?string
    {
        return $this->date_taken ? $this->date_taken->format('d.m.Y') : null;
    }

    /**
     * Get the license display name.
     */
    public function getLicenseDisplayNameAttribute(): string
    {
        if ($this->license_shortname) {
            return $this->license_shortname;
        }
        return match($this->license) {
            'cc-by-sa-4.0' => 'CC BY-SA 4.0',
            'cc-by-sa-3.0' => 'CC BY-SA 3.0',
            'cc-by-4.0' => 'CC BY 4.0',
            'cc-by-3.0' => 'CC BY 3.0',
            'cc0' => 'CC0',
            default => $this->license ?? 'Unknown',
        };
    }
}
