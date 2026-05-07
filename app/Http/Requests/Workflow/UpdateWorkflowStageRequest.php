<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'         => ['sometimes', 'string', 'max:50'],
            'sort_order'   => ['sometimes', 'integer', 'min:0'],
            'is_active'    => ['sometimes', 'boolean'],
            'is_terminal'  => ['sometimes', 'boolean'],
            'color'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'translations' => ['sometimes', 'array'],
            'translations.name'    => ['sometimes', 'array'],
            'translations.name.es' => ['sometimes', 'required_with:translations.name', 'string', 'max:255'],
            'translations.name.en' => ['nullable', 'string', 'max:255'],
            'translations.name.fr' => ['nullable', 'string', 'max:255'],
            'translations.description'    => ['nullable', 'array'],
            'translations.description.es' => ['nullable', 'string'],
            'translations.description.en' => ['nullable', 'string'],
            'translations.description.fr' => ['nullable', 'string'],
        ];
    }
}
