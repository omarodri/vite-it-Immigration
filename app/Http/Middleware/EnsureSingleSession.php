<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->security_locked_until && $user->security_locked_until->isFuture()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message'      => 'This account is temporarily locked due to suspicious activity.',
                'reason'       => 'account_security_locked',
                'locked_until' => $user->security_locked_until->toIso8601String(),
            ], 401);
        }

        $currentSessionId = $request->session()->getId();

        if ($user->current_session_id !== null
            && $user->current_session_id !== $currentSessionId) {

            // Revoke this stale session cleanly
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message'    => 'Your session was ended because you signed in from another device or browser.',
                'reason'     => 'session_revoked',
                'revoked_at' => $user->session_invalidated_at?->toIso8601String(),
            ], 401);
        }

        return $next($request);
    }
}
