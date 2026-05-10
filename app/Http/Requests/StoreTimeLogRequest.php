<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeLogRequest extends FormRequest
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
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'work_date'        => ['nullable', 'date', 'before_or_equal:today'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'todo_id'          => ['nullable', 'integer', 'exists:todos,id'],
        ];
    }
}
