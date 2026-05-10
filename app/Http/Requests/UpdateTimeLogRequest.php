<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'duration_seconds' => ['sometimes', 'integer', 'min:1', 'max:86400'],
            'work_date'        => ['sometimes', 'date', 'before_or_equal:today'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'todo_id'          => ['nullable', 'integer', 'exists:todos,id'],
        ];
    }
}
