<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return ! empty($this->wikimedia_id) && ! empty($this->wikimedia_username);
    }

    /**
     * Check if user has Commons edit permission.
     */
    public function canEditCommons(): bool
    {
        return $this->has_commons_edit_permission;
    }

    /**
     * Check if user has a password (for traditional login).
     */
    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }

    /**
     * Check if user can use traditional login (has password).
     */
    public function canUseTraditionalLogin(): bool
    {
        return $this->hasPassword();
    }

    /**
     * Get the display name (Wikimedia username preferred).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->wikimedia_username ?? $this->name ?? 'Unknown User';
    }

    /**
     * Get Wikimedia access token from session.
     */
    public function getWikimediaAccessToken(): ?string
    {
        return session('wikimedia_access_token');
    }

    /**
     * Get Wikimedia refresh token from session.
     */
    public function getWikimediaRefreshToken(): ?string
    {
        return session('wikimedia_refresh_token');
    }

    /**
     * Get Wikimedia token expiration time from session.
     */
    public function getWikimediaTokenExpiresAt(): ?\Carbon\Carbon
    {
        $expiresAt = session('wikimedia_token_expires_at');

        return $expiresAt ? \Carbon\Carbon::parse($expiresAt) : null;
    }

    /**
     * Check if user's Wikimedia token is expired.
     */
    public function isWikimediaTokenExpired(): bool
    {
        $expiresAt = $this->getWikimediaTokenExpiresAt();

        return $expiresAt && $expiresAt->isPast();
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
