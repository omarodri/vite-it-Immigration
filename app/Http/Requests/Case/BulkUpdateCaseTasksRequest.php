<?php

namespace App\Http\Requests\Case;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCaseTasksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'max:50'],
            'tasks.*.label' => ['required', 'string', 'max:150'],
            'tasks.*.is_completed' => ['boolean'],
            'tasks.*.is_custom' => ['boolean'],
            'tasks.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tasks.required' => 'Task list is required.',
            'tasks.*.label.required' => 'Each task must have a label.',
            'tasks.*.label.max' => 'Task label cannot exceed 150 characters.',
        ];
    }
}
