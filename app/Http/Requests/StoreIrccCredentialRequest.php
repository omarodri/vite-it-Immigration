<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Spec 60 — Validation for storing/updating IRCC credentials.
 *
 * Authorization is delegated to the controller via Gate ability checks,
 * so authorize() returns true here.
 */
class StoreIrccCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ircc_username'                  => 'nullable|string|max:255',
            'ircc_password'                  => 'nullable|string|max:255',
            'email_address'                  => 'nullable|email|max:255',
            'email_password'                 => 'nullable|string|max:255',
            'application_number'             => 'nullable|string|max:50',
            'notes'                          => 'nullable|string|max:5000',
            'security_questions'             => 'nullable|array|size:5',
            'security_questions.*.pregunta'  => 'nullable|string|max:500',
            'security_questions.*.respuesta' => 'nullable|string|max:500',
        ];
    }
}
