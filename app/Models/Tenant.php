<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'ms_client_id',
        'ms_client_secret',
        'google_client_id',
        'google_client_secret',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'ms_client_secret',
        'google_client_secret',
    ];

    /**
     * Get the users for this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the clients for this tenant.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Get the cases for this tenant.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class);
    }

    /**
     * Set the Microsoft client secret (encrypted).
     */
    public function setMsClientSecretAttribute(?string $value): void
    {
        $this->attributes['ms_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get the Microsoft client secret (decrypted).
     */
    public function getMsClientSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Set the Google client secret (encrypted).
     */
    public function setGoogleClientSecretAttribute(?string $value): void
    {
        $this->attributes['google_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get the Google client secret (decrypted).
     */
    public function getGoogleClientSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        return $this;
    }

    /**
     * Check if tenant has Microsoft OAuth configured.
     */
    public function hasMicrosoftOAuth(): bool
    {
        return !empty($this->ms_client_id) && !empty($this->attributes['ms_client_secret']);
    }

    /**
     * Check if tenant has Google OAuth configured.
     */
    public function hasGoogleOAuth(): bool
    {
        return !empty($this->google_client_id) && !empty($this->attributes['google_client_secret']);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
