<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentResource;
use App\Models\Client;
use App\Models\Companion;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpirationAlertController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/expiration-alerts
     *
     * List legal documents that are expiring soon, with optional filters.
     *
     * Query parameters:
     *   - status: overdue|critical|warning|all (default: all expiring soon)
     *   - entity_type: client|companion|all (default: all)
     *   - search: free-text search on entity name, document_number, document_type
     *   - per_page: pagination size (default: 25)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalDocument::class);

        $user = $request->user();

        $query = LegalDocument::query()
            ->where('tenant_id', $user->tenant_id)
            ->expiringSoon()
            ->with('documentable');

        // ── Status filter ──────────────────────────────────────────────
        $status = $request->input('status', 'all');

        if ($status === 'overdue') {
            $query->where('expiry_date', '<', now()->startOfDay());
        } elseif ($status === 'critical') {
            $query->where('expiry_date', '>=', now()->startOfDay())
                  ->where('expiry_date', '<=', now()->addDays(60)->endOfDay());
        } elseif ($status === 'warning') {
            $query->where('expiry_date', '>', now()->addDays(60)->endOfDay())
                  ->where('expiry_date', '<=', now()->addDays(90)->endOfDay());
        }
        // 'all' => no additional filter (expiringSoon scope already applied)

        // ── Entity type filter ─────────────────────────────────────────
        $entityType = $request->input('entity_type', 'all');

        if ($entityType === 'client') {
            $query->where('documentable_type', Client::class);
        } elseif ($entityType === 'companion') {
            $query->where('documentable_type', Companion::class);
        }

        // ── Search filter ──────────────────────────────────────────────
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('document_type', 'like', "%{$search}%")
                  ->orWhere('document_name', 'like', "%{$search}%")
                  ->orWhereHasMorph('documentable', [Client::class, Companion::class], function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        return LegalDocumentResource::collection(
            $query->orderBy('expiry_date')
                  ->paginate((int) $request->input('per_page', 25))
        );
    }
}
