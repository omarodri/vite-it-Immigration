<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    use HasFactory;

    /**
     * Storage type constants.
     */
    public const STORAGE_LOCAL = 'local';

    public const STORAGE_ONEDRIVE = 'onedrive';

    public const STORAGE_GOOGLE_DRIVE = 'google_drive';

    public const STORAGE_SHAREPOINT = 'sharepoint';

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'ms_client_id',
        'ms_client_secret',
        'google_client_id',
        'google_client_secret',
        'storage_type',
        'sharepoint_site_id',
        'sharepoint_drive_id',
        'sharepoint_site_url',
        'base_folder_path',
        'base_folder_external_id',
        'storage_quota_mb',
        'storage_used_bytes',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'storage_quota_mb' => 'integer',
        'storage_used_bytes' => 'integer',
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
     * Get the invitation codes for this tenant.
     */
    public function invitationCodes(): HasMany
    {
        return $this->hasMany(InvitationCode::class);
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
     * Get storage used in megabytes.
     */
    public function getStorageUsedMb(): float
    {
        return round((int) $this->storage_used_bytes / (1024 * 1024), 2);
    }

    /**
     * Get storage usage as a percentage of the quota.
     */
    public function getStorageUsagePercent(): float
    {
        $quotaMb = (int) $this->storage_quota_mb;

        if ($quotaMb <= 0) {
            return 0.0;
        }

        return round(($this->getStorageUsedMb() / $quotaMb) * 100, 2);
    }

    /**
     * Check if the tenant has exceeded their storage quota.
     */
    public function isStorageQuotaExceeded(): bool
    {
        return $this->getStorageUsagePercent() >= 100.0;
    }

    /**
     * Check if the tenant is approaching their storage quota (80% threshold).
     */
    public function hasStorageWarning(): bool
    {
        return $this->getStorageUsagePercent() >= 80.0;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
