<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        'wikidata_id',
        'name_tr',
        'name_en',
        'description_tr',
        'description_en',
        'parent_category_id',
        'icon',
        'color',
        'is_active',
        'monument_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monument_count' => 'integer',
    ];

    /**
     * Get the monuments in this category.
     */
    public function monuments(): BelongsToMany
    {
        return $this->belongsToMany(Monument::class, 'category_monument');
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'categories', 'parent_category_id', 'id');
    }

    /**
     * Get the primary name (Turkish preferred, fallback to English).
     */
    public function getPrimaryNameAttribute(): string
    {
        return $this->name_tr ?? $this->name_en ?? 'Unnamed Category';
    }

    /**
     * Get the primary description (Turkish preferred, fallback to English).
     */
    public function getPrimaryDescriptionAttribute(): ?string
    {
        return $this->description_tr ?? $this->description_en;
    }

    /**
     * Scope to filter active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter root categories (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_category_id');
    }

    /**
     * Scope to filter categories with monuments.
     */
    public function scopeWithMonuments($query)
    {
        return $query->where('monument_count', '>', 0);
    }

    /**
     * Get all descendants of this category.
     */
    public function getAllDescendantsAttribute()
    {
        $descendants = collect();
        $children = $this->children;

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->all_descendants);
        }

        return $descendants;
    }

    /**
     * Get all ancestors of this category.
     */
    public function getAllAncestorsAttribute()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full category path.
     */
    public function getFullPathAttribute(): string
    {
        $path = collect($this->all_ancestors);
        $path->push($this);
        
        return $path->pluck('primary_name')->implode(' > ');
    }

    /**
     * Update the monument count for this category.
     */
    public function updateMonumentCount(): void
    {
        $this->update([
            'monument_count' => $this->monuments()->count()
        ]);
    }
}
