<?php

namespace App\Http\Requests\CaseType;

use App\Models\CaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via $this->authorize().
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name'        => ['required', 'string', 'max:120'],
            'code'        => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9_]+$/i',
                // Unique por tenant (excluye trashed — soft-deleted libera el code).
                Rule::unique('case_types')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'category'    => ['required', Rule::in(CaseType::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
        ];
    }
}
