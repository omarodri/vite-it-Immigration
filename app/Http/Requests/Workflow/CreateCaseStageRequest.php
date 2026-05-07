<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class CreateCaseStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'array'],
            'name.es'        => ['required', 'string', 'max:120'],
            'name.en'        => ['nullable', 'string', 'max:120'],
            'name.fr'        => ['nullable', 'string', 'max:120'],
            'description'    => ['nullable', 'array'],
            'description.es' => ['nullable', 'string', 'max:500'],
            'description.en' => ['nullable', 'string', 'max:500'],
            'description.fr' => ['nullable', 'string', 'max:500'],
            'color'          => ['required', 'string', 'in:primary,secondary,success,danger,warning,info,dark'],
            'is_terminal'    => ['boolean'],
        ];
    }
}
