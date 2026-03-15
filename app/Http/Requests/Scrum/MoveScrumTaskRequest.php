<?php

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class MoveScrumTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scrum_column_id' => ['required', 'integer', 'exists:scrum_columns,id'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
