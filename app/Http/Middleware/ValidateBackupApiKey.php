<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBackupApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('backup.api_key', '');

        if (empty($configuredKey)) {
            abort(503, 'Backup API key not configured.');
        }

        $providedKey = $request->header('X-Backup-Key', '');

        if (! hash_equals($configuredKey, $providedKey)) {
            abort(401, 'Invalid backup API key.');
        }

        return $next($request);
    }
}
