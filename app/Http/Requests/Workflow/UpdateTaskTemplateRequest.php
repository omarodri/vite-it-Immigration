<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                    => ['sometimes', 'string', 'max:80'],
            'sort_order'              => ['sometimes', 'integer', 'min:0'],
            'is_required'             => ['sometimes', 'boolean'],
            'blocks_stage_completion' => ['sometimes', 'boolean'],
            'default_type'            => ['sometimes', 'nullable', 'in:translation,case_creation,accounting,filing,document,other'],
            'default_priority'        => ['sometimes', 'nullable', 'in:urgent,high,medium,low'],
            'due_offset_days'         => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'is_active'               => ['sometimes', 'boolean'],
            'translations'            => ['sometimes', 'array'],
            'translations.name'       => ['sometimes', 'array'],
            'translations.name.es'    => ['sometimes', 'required_with:translations.name', 'string', 'max:255'],
            'translations.name.en'    => ['nullable', 'string', 'max:255'],
            'translations.name.fr'    => ['nullable', 'string', 'max:255'],
            'translations.description'    => ['nullable', 'array'],
            'translations.description.es' => ['nullable', 'string'],
            'translations.description.en' => ['nullable', 'string'],
            'translations.description.fr' => ['nullable', 'string'],
        ];
    }
}
