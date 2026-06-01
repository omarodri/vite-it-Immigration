<?php

namespace App\Http\Requests\CaseType;

use App\Models\CaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseTypeRequest extends FormRequest
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
        $id       = $this->route('case_type')?->id;

        return [
            'name'        => ['sometimes', 'string', 'max:120'],
            'code'        => [
                'sometimes', 'string', 'max:10', 'regex:/^[A-Z0-9_]+$/i',
                Rule::unique('case_types')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'category'    => ['sometimes', Rule::in(CaseType::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
        ];
    }
}
