<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'array'],
            'name.es'        => ['sometimes', 'string', 'max:120'],
            'name.en'        => ['sometimes', 'nullable', 'string', 'max:120'],
            'name.fr'        => ['sometimes', 'nullable', 'string', 'max:120'],
            'description'    => ['sometimes', 'array'],
            'description.es' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description.en' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description.fr' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color'          => ['sometimes', 'string', 'in:primary,secondary,success,danger,warning,info,dark'],
            'is_terminal'    => ['sometimes', 'boolean'],
        ];
    }
}
