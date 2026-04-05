<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalDocument;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type'    => ['sometimes', 'string', Rule::in(LegalDocument::DOCUMENT_TYPES)],
            'document_name'    => ['nullable', 'string', 'max:150', 'required_if:document_type,other'],
            'document_number'  => ['nullable', 'string', 'max:80'],
            'issuing_country'  => ['nullable', 'string', 'max:100'],
            'issue_date'       => ['nullable', 'date'],
            'expiry_date'      => ['nullable', 'date'],
            'alert_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'alert_active'     => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.in'           => 'Invalid document type.',
            'document_name.required_if'  => 'A document name is required when the type is "other".',
            'document_name.max'          => 'The document name must not exceed 150 characters.',
            'document_number.max'        => 'The document number must not exceed 80 characters.',
            'alert_days_before.min'      => 'Alert days must be at least 1.',
            'alert_days_before.max'      => 'Alert days must not exceed 365.',
            'notes.max'                  => 'Notes must not exceed 1000 characters.',
        ];
    }
}
