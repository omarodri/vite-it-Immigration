<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'string', 'max:50'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_active'    => ['boolean'],
            'is_terminal'  => ['boolean'],
            'color'        => ['nullable', 'string', 'max:20'],
            'translations' => ['required', 'array'],
            'translations.name'    => ['required', 'array'],
            'translations.name.es' => ['required', 'string', 'max:255'],
            'translations.name.en' => ['nullable', 'string', 'max:255'],
            'translations.name.fr' => ['nullable', 'string', 'max:255'],
            'translations.description'    => ['nullable', 'array'],
            'translations.description.es' => ['nullable', 'string'],
            'translations.description.en' => ['nullable', 'string'],
            'translations.description.fr' => ['nullable', 'string'],
        ];
    }
}
