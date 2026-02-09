<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
