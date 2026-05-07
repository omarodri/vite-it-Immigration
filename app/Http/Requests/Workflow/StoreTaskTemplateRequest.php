<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                    => ['required', 'string', 'max:80'],
            'sort_order'              => ['nullable', 'integer', 'min:0'],
            'is_required'             => ['boolean'],
            'blocks_stage_completion' => ['boolean'],
            'default_type'            => ['nullable', 'in:translation,case_creation,accounting,filing,document,other'],
            'default_priority'        => ['nullable', 'in:urgent,high,medium,low'],
            'due_offset_days'         => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active'               => ['boolean'],
            'translations'            => ['required', 'array'],
            'translations.name'       => ['required', 'array'],
            'translations.name.es'    => ['required', 'string', 'max:255'],
            'translations.name.en'    => ['nullable', 'string', 'max:255'],
            'translations.name.fr'    => ['nullable', 'string', 'max:255'],
            'translations.description'    => ['nullable', 'array'],
            'translations.description.es' => ['nullable', 'string'],
            'translations.description.en' => ['nullable', 'string'],
            'translations.description.fr' => ['nullable', 'string'],
        ];
    }
}
