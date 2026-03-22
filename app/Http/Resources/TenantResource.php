<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->settings ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,

            // Branding
            'branding' => [
                'logo_url' => $settings['logo_url'] ?? null,
                'primary_color' => $settings['primary_color'] ?? '#4361ee',
                'secondary_color' => $settings['secondary_color'] ?? '#805dca',
            ],

            // Company Info
            'company' => [
                'name' => $settings['company_name'] ?? $this->name,
                'email' => $settings['company_email'] ?? null,
                'phone' => $settings['company_phone'] ?? null,
                'address' => $settings['company_address'] ?? null,
                'website' => $settings['company_website'] ?? null,
            ],

            // Preferences
            'preferences' => [
                'timezone' => $settings['timezone'] ?? 'America/Toronto',
                'date_format' => $settings['date_format'] ?? 'Y-m-d',
                'language' => $settings['language'] ?? 'es',
            ],

            // Theme
            'theme' => [
                'mode' => $this->getSetting('theme.mode', 'light'),
                'menu' => $this->getSetting('theme.menu', 'vertical'),
                'layout' => $this->getSetting('theme.layout', 'full'),
                'rtl_class' => $this->getSetting('theme.rtl_class', 'ltr'),
                'animation' => $this->getSetting('theme.animation', 'animate__fadeIn'),
                'navbar' => $this->getSetting('theme.navbar', 'navbar-sticky'),
                'semidark' => (bool) $this->getSetting('theme.semidark', false),
                'show_customizer' => (bool) $this->getSetting('theme.show_customizer', true),
            ],

            // OAuth Status (only show if configured, not the actual credentials)
            'integrations' => [
                'microsoft_configured' => $this->hasMicrosoftOAuth(),
                'google_configured' => $this->hasGoogleOAuth(),
            ],

            // Storage info
            'storage_type' => $this->storage_type ?? 'local',
            'storage_quota_mb' => $this->storage_quota_mb,

            // Conditional: included when withCount('users') is loaded
            'users_count' => $this->whenCounted('users'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
