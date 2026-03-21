<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Support\Facades\Auth;

class DocumentAuditService
{
    /**
     * Log a document access event using spatie/laravel-activitylog.
     *
     * @param  string  $action  One of: viewed, downloaded, previewed, uploaded, deleted, moved, replaced
     */
    public function logAccess(Document $document, string $action, array $properties = []): void
    {
        $defaultProperties = [
            'action' => $action,
            'case_id' => $document->case_id,
            'document_id' => $document->id,
            'original_name' => $document->original_name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        $mergedProperties = array_merge($defaultProperties, $properties);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($document)
            ->withProperties($mergedProperties)
            ->useLog('document_access')
            ->log(ucfirst($action) . ' document: ' . $document->original_name);
    }
}
