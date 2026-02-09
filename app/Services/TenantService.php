<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    /**
     * Update tenant settings.
     */
    public function updateSettings(Tenant $tenant, array $data): Tenant
    {
        $settings = $tenant->settings ?? [];

        // Merge new settings with existing
        $settings = array_merge($settings, [
            'company_name' => $data['company_name'] ?? $settings['company_name'] ?? null,
            'company_email' => $data['company_email'] ?? $settings['company_email'] ?? null,
            'company_phone' => $data['company_phone'] ?? $settings['company_phone'] ?? null,
            'company_address' => $data['company_address'] ?? $settings['company_address'] ?? null,
            'company_website' => $data['company_website'] ?? $settings['company_website'] ?? null,
            'timezone' => $data['timezone'] ?? $settings['timezone'] ?? 'America/Toronto',
            'date_format' => $data['date_format'] ?? $settings['date_format'] ?? 'Y-m-d',
            'language' => $data['language'] ?? $settings['language'] ?? 'es',
        ]);

        $tenant->settings = $settings;

        // Update name if provided
        if (isset($data['name'])) {
            $tenant->name = $data['name'];
        }

        $tenant->save();

        return $tenant->fresh();
    }

    /**
     * Update tenant branding settings.
     */
    public function updateBranding(Tenant $tenant, array $data): Tenant
    {
        $settings = $tenant->settings ?? [];

        if (isset($data['logo_url'])) {
            $settings['logo_url'] = $data['logo_url'];
        }

        if (isset($data['primary_color'])) {
            $settings['primary_color'] = $data['primary_color'];
        }

        if (isset($data['secondary_color'])) {
            $settings['secondary_color'] = $data['secondary_color'];
        }

        $tenant->settings = $settings;
        $tenant->save();

        return $tenant->fresh();
    }

    /**
     * Get tenant branding by slug (for public access).
     */
    public function getBrandingBySlug(string $slug): ?array
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return null;
        }

        $settings = $tenant->settings ?? [];

        return [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $settings['logo_url'] ?? null,
            'primary_color' => $settings['primary_color'] ?? '#4361ee',
            'secondary_color' => $settings['secondary_color'] ?? '#805dca',
            'company_name' => $settings['company_name'] ?? $tenant->name,
        ];
    }

    /**
     * Create a new tenant.
     */
    public function create(array $data): Tenant
    {
        return Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'settings' => [
                'logo_url' => $data['logo_url'] ?? null,
                'primary_color' => $data['primary_color'] ?? '#4361ee',
                'secondary_color' => $data['secondary_color'] ?? '#805dca',
                'company_name' => $data['company_name'] ?? $data['name'],
                'company_email' => $data['company_email'] ?? null,
                'company_phone' => $data['company_phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Toronto',
                'language' => $data['language'] ?? 'es',
            ],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
