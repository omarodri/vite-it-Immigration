<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spec 60 — IRCC credentials transformer.
 *
 * Surfaces decrypted plaintext only to authorized callers (the controller
 * already enforced the `ircc_credentials.view` Gate before resolving this
 * resource).
 *
 * When the underlying CaseIrccCredential does not yet exist for a case
 * (`$this->resource === null`), this resource returns an empty-shaped
 * payload so the SPA can render an editable form without special-casing
 * the "first time" flow.
 *
 * IMPORTANT: this resource is dedicated to the IRCC endpoint only. It must
 * NEVER be embedded inside CaseResource or any list endpoint — credentials
 * surface only through their dedicated route.
 */
class IrccCredentialResource extends JsonResource
{
    /**
     * Default empty 5-slot Q&A structure used both when no record exists
     * and as a fallback for legacy records with NULL security_questions.
     *
     * @return array<int, array{pregunta: string, respuesta: string}>
     */
    private static function emptyQuestions(): array
    {
        return array_fill(0, 5, ['pregunta' => '', 'respuesta' => '']);
    }

    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [
                'exists'             => false,
                'ircc_username'      => null,
                'ircc_password'      => null,
                'email_address'      => null,
                'email_password'     => null,
                'application_number' => null,
                'notes'              => null,
                'security_questions' => self::emptyQuestions(),
                'created_by_name'    => null,
                'updated_at'         => null,
            ];
        }

        return [
            'exists'             => true,
            'ircc_username'      => $this->ircc_username,
            'ircc_password'      => $this->ircc_password,
            'email_address'      => $this->email_address,
            'email_password'     => $this->email_password,
            'application_number' => $this->application_number,
            'notes'              => $this->notes,
            'security_questions' => $this->security_questions ?: self::emptyQuestions(),
            'created_by_name'    => $this->createdBy?->name,
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
