<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {}

    /**
     * List all tenants (paginated with search/filter).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tenant::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $tenants = $query->withCount('users')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return TenantResource::collection($tenants);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:tenants,slug', 'regex:/^[a-z0-9-]+$/'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'storage_type' => ['nullable', 'string', 'in:local,onedrive,google_drive'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:100'],
        ]);

        $tenant = $this->tenantService->create($validated);

        return (new TenantResource($tenant))
            ->additional(['message' => 'Tenant created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific tenant with stats.
     */
    public function show(int $id): TenantResource
    {
        $tenant = Tenant::withCount('users')->findOrFail($id);

        return (new TenantResource($tenant))->additional([
            'storage' => [
                'used_mb' => $tenant->getStorageUsedMb(),
                'quota_mb' => $tenant->storage_quota_mb,
                'usage_percent' => $tenant->getStorageUsagePercent(),
            ],
        ]);
    }

    /**
     * Update a tenant.
     */
    public function update(Request $request, int $id): TenantResource
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', 'unique:tenants,slug,' . $tenant->id, 'regex:/^[a-z0-9-]+$/'],
            'is_active' => ['sometimes', 'boolean'],
            'storage_type' => ['sometimes', 'string', 'in:local,onedrive,google_drive'],
            'storage_quota_mb' => ['sometimes', 'integer', 'min:100'],
        ]);

        $tenant->update($validated);

        return (new TenantResource($tenant->fresh()))
            ->additional(['message' => 'Tenant updated successfully.']);
    }

    /**
     * Deactivate a tenant (soft approach — no hard delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => false]);

        return response()->json(['message' => 'Tenant deactivated successfully.']);
    }

    /**
     * Activate a tenant.
     */
    public function activate(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => true]);

        return response()->json(['message' => 'Tenant activated successfully.']);
    }

    /**
     * Get summary statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'total_users' => User::count(),
            'total_storage_used_mb' => round(
                (int) Tenant::sum('storage_used_bytes') / (1024 * 1024),
                2
            ),
        ]);
    }
}
