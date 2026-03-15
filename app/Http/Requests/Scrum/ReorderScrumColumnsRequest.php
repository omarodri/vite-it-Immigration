<?php

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class ReorderScrumColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'columns' => ['required', 'array'],
            'columns.*.id' => ['required', 'integer', 'exists:scrum_columns,id'],
            'columns.*.order_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
