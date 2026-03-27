<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Services\OAuthTokenService;
use App\Services\Storage\StorageProviderFactory;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            'storage_type' => ['required', 'string', 'in:local,onedrive,google_drive,sharepoint'],
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
     * List available SharePoint sites for the tenant.
     */
    public function listSharePointSites()
    {
        $tenant = Auth::user()->tenant;

        if (!$tenant->hasMicrosoftOAuth()) {
            return response()->json(['message' => 'Microsoft OAuth not configured'], 422);
        }

        $tokenService = app(OAuthTokenService::class);
        $accessToken = $tokenService->getValidTenantToken($tenant->id, 'microsoft');

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get('https://graph.microsoft.com/v1.0/sites', [
                'search' => '*',
                '$select' => 'id,displayName,webUrl',
                '$top' => 100,
            ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Failed to fetch SharePoint sites'], $response->status());
        }

        return response()->json(['data' => $response->json('value', [])]);
    }

    /**
     * List drives for a specific SharePoint site.
     */
    public function listSharePointDrives(string $siteId)
    {
        $tenant = Auth::user()->tenant;

        if (!$tenant->hasMicrosoftOAuth()) {
            return response()->json(['message' => 'Microsoft OAuth not configured'], 422);
        }

        $tokenService = app(OAuthTokenService::class);
        $accessToken = $tokenService->getValidTenantToken($tenant->id, 'microsoft');

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get("https://graph.microsoft.com/v1.0/sites/{$siteId}/drives", [
                '$select' => 'id,name,driveType,webUrl',
            ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Failed to fetch drives'], $response->status());
        }

        return response()->json(['data' => $response->json('value', [])]);
    }

    /**
     * Save SharePoint site and drive configuration for the tenant.
     */
    public function saveSharePointConfig(Request $request)
    {
        $validated = $request->validate([
            'sharepoint_site_id' => 'required|string|max:255',
            'sharepoint_drive_id' => 'required|string|max:255',
            'sharepoint_site_url' => 'nullable|string|max:500',
        ]);

        $tenant = Auth::user()->tenant;
        $tenant->update($validated);

        return (new TenantResource($tenant->fresh()))
            ->additional(['message' => 'SharePoint configuration saved successfully.']);
    }

    /**
     * Update the base folder path for the tenant.
     */
    public function updateBaseFolder(Request $request)
    {
        $validated = $request->validate([
            'base_folder_path' => ['nullable', 'string', 'max:255', 'regex:/^[^\/\\\\:*?"<>|]+$/'],
        ]);

        $tenant = Auth::user()->tenant;

        $baseFolderPath = $validated['base_folder_path'] ?: null;
        $baseFolderExternalId = null;

        if ($baseFolderPath && $tenant->storage_type !== 'local') {
            try {
                $factory = app(StorageProviderFactory::class);
                $provider = $factory->makeForTenant($tenant);
                $result = $provider->createFolder($baseFolderPath);
                $baseFolderExternalId = $result['external_id'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Failed to create base folder in cloud', [
                    'tenant_id' => $tenant->id,
                    'base_folder_path' => $baseFolderPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $tenant->update([
            'base_folder_path' => $baseFolderPath,
            'base_folder_external_id' => $baseFolderExternalId,
        ]);

        return (new TenantResource($tenant->fresh()))
            ->additional(['message' => 'Base folder configuration saved successfully.']);
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
