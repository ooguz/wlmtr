<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'wikimedia_id',
        'wikimedia_username',
        'wikimedia_real_name',
        'wikimedia_groups',
        'wikimedia_rights',
        'wikimedia_edit_count',
        'wikimedia_registration_date',
        'wikimedia_access_token',
        'wikimedia_refresh_token',
        'wikimedia_token_expires_at',
        'has_commons_edit_permission',
        'last_wikimedia_sync',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'wikimedia_access_token',
        'wikimedia_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wikimedia_groups' => 'array',
            'wikimedia_rights' => 'array',
            'wikimedia_registration_date' => 'datetime',
            'wikimedia_token_expires_at' => 'datetime',
            'has_commons_edit_permission' => 'boolean',
            'last_wikimedia_sync' => 'datetime',
        ];
    }

    /**
     * Get the photos uploaded by this user.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Check if user is connected to Wikimedia.
     */
    public function isWikimediaConnected(): bool
    {
        return !empty($this->wikimedia_id) && !empty($this->wikimedia_username);
    }

    /**
     * Check if user has Commons edit permission.
     */
    public function canEditCommons(): bool
    {
        return $this->has_commons_edit_permission;
    }

    /**
     * Get the display name (Wikimedia username preferred).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->wikimedia_username ?? $this->name ?? 'Unknown User';
    }

    /**
     * Check if user's Wikimedia token is expired.
     */
    public function isWikimediaTokenExpired(): bool
    {
        return $this->wikimedia_token_expires_at && 
               $this->wikimedia_token_expires_at->isPast();
    }

    /**
     * Get user's upload count on this platform.
     */
    public function getUploadCountAttribute(): int
    {
        return $this->photos()->count();
    }

    /**
     * Get user's total edit count (Wikimedia + platform uploads).
     */
    public function getTotalEditCountAttribute(): int
    {
        return $this->wikimedia_edit_count + $this->upload_count;
    }
}
