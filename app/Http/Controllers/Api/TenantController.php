<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $tenantService
    ) {}

    /**
     * Get the current tenant's information.
     */
    public function show(Request $request): TenantResource
    {
        $tenant = $request->user()->tenant;

        return new TenantResource($tenant);
    }

    /**
     * Update the current tenant's settings.
     */
    public function updateSettings(UpdateTenantSettingsRequest $request): TenantResource
    {
        $tenant = $request->user()->tenant;

        $tenant = $this->tenantService->updateSettings(
            $tenant,
            $request->validated()
        );

        return new TenantResource($tenant);
    }

    /**
     * Update the current tenant's branding.
     */
    public function updateBranding(Request $request): TenantResource
    {
        $request->validate([
            'logo_url' => ['nullable', 'url', 'max:500'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $tenant = $request->user()->tenant;

        $tenant = $this->tenantService->updateBranding(
            $tenant,
            $request->only(['logo_url', 'primary_color', 'secondary_color'])
        );

        return new TenantResource($tenant);
    }

    /**
     * Upload tenant logo.
     */
    public function uploadLogo(Request $request): TenantResource
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        $tenant = $request->user()->tenant;
        $file = $request->file('logo');

        // Delete old logo if exists
        $oldLogoUrl = $tenant->getSetting('branding.logo_url');
        if ($oldLogoUrl) {
            $oldPath = str_replace('/storage/', '', parse_url($oldLogoUrl, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $file->storeAs(
            "tenants/{$tenant->slug}",
            'logo.' . $file->extension(),
            'public'
        );

        $logoUrl = Storage::disk('public')->url($path) . '?v=' . time();

        $tenant = $this->tenantService->updateBranding($tenant, [
            'logo_url' => $logoUrl,
        ]);

        return new TenantResource($tenant);
    }

    /**
     * Delete tenant logo.
     */
    public function deleteLogo(Request $request): TenantResource
    {
        $tenant = $request->user()->tenant;

        $logoUrl = $tenant->getSetting('logo_url');
        if ($logoUrl) {
            $oldPath = str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $tenant = $this->tenantService->updateBranding($tenant, [
            'logo_url' => null,
        ]);

        return new TenantResource($tenant);
    }

    /**
     * Update tenant storage type.
     */
    public function updateStorageType(Request $request): TenantResource
    {
        $request->validate([
            'storage_type' => ['required', 'string', 'in:local,onedrive,google_drive'],
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update(['storage_type' => $request->input('storage_type')]);

        return new TenantResource($tenant->fresh());
    }

    /**
     * Update tenant theme settings.
     */
    public function updateTheme(Request $request): TenantResource
    {
        $request->validate([
            'mode' => ['nullable', 'string', 'in:light,dark,system'],
            'menu' => ['nullable', 'string', 'in:vertical,collapsible-vertical,horizontal'],
            'layout' => ['nullable', 'string', 'in:full,boxed-layout'],
            'rtl_class' => ['nullable', 'string', 'in:ltr,rtl'],
            'animation' => ['nullable', 'string'],
            'navbar' => ['nullable', 'string', 'in:navbar-sticky,navbar-floating,navbar-static'],
            'semidark' => ['nullable', 'boolean'],
            'show_customizer' => ['nullable', 'boolean'],
        ]);

        $tenant = $request->user()->tenant;
        $tenant = $this->tenantService->updateThemeSettings($tenant, $request->all());

        return new TenantResource($tenant);
    }

    /**
     * Get tenant branding for public display (no auth required).
     */
    public function branding(string $slug): JsonResponse
    {
        $branding = $this->tenantService->getBrandingBySlug($slug);

        if (!$branding) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        return response()->json($branding);
    }
}
