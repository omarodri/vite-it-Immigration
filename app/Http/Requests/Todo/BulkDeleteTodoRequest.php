<?php

namespace App\Http\Requests\Todo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteTodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('todos', 'id')->where(
                    fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)
                ),
            ],
        ];
    }
}
