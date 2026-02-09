<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user belongs to an active tenant.
     * Super admins bypass this check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow unauthenticated requests to pass through
        // (authentication middleware should handle auth requirements)
        if (!$user) {
            return $next($request);
        }

        // Super admins can access without tenant
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Check if user has a tenant
        if (!$user->tenant_id) {
            return response()->json([
                'message' => 'User is not associated with any organization.',
                'error' => 'no_tenant',
            ], 403);
        }

        // Check if tenant is active
        if (!$user->tenant || !$user->tenant->is_active) {
            return response()->json([
                'message' => 'Your organization is currently inactive. Please contact support.',
                'error' => 'tenant_inactive',
            ], 403);
        }

        return $next($request);
    }
}
