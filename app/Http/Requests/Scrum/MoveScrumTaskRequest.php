<?php

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveScrumTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Scoped to the user's tenant — a bare exists rule would accept another tenant's column ID
            'scrum_column_id' => [
                'required',
                'integer',
                Rule::exists('scrum_columns', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
