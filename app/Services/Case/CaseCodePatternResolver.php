<?php

namespace App\Services\Case;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Spec 65 — Resolves the active {@see CaseCodePattern} for a tenant.
 *
 * Cached via {@see Cache::remember()} (1 hour) so the hot path of case
 * creation does not hit MySQL just to read three JSON keys. Cache is
 * cross-process (driver: database/redis) so queue workers see updates
 * after the admin panel calls {@see invalidate()}.
 */
final class CaseCodePatternResolver
{
    private const CACHE_TTL = 3600;
    private const CACHE_KEY = 'tenant:%d:case_code_pattern';

    public function forTenant(Tenant $tenant): CaseCodePattern
    {
        return Cache::remember(
            sprintf(self::CACHE_KEY, $tenant->id),
            self::CACHE_TTL,
            fn () => CaseCodePattern::fromSettings($tenant->settings ?? [])
        );
    }

    public function invalidate(Tenant $tenant): void
    {
        Cache::forget(sprintf(self::CACHE_KEY, $tenant->id));
    }
}
