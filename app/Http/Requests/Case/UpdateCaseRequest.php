<?php

namespace App\Http\Requests\Case;

use App\Models\ImmigrationCase;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $this->user()->can('update', $case);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in([
                ImmigrationCase::STATUS_ACTIVE,
                ImmigrationCase::STATUS_INACTIVE,
                ImmigrationCase::STATUS_ARCHIVED,
                ImmigrationCase::STATUS_CLOSED,
            ])],
            'priority' => ['sometimes', Rule::in([
                ImmigrationCase::PRIORITY_URGENT,
                ImmigrationCase::PRIORITY_HIGH,
                ImmigrationCase::PRIORITY_MEDIUM,
                ImmigrationCase::PRIORITY_LOW,
            ])],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'language' => ['sometimes', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hearing_date' => ['nullable', 'date'],
            'fda_deadline' => ['nullable', 'date'],
            'brown_sheet_date' => ['nullable', 'date'],
            'evidence_deadline' => ['nullable', 'date'],
            'archive_box_number' => ['nullable', 'string', 'max:50'],
            'closure_notes' => ['nullable', 'string', 'max:2000', 'required_if:status,closed'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->assigned_to) {
                $assignedUser = User::withoutGlobalScopes()->find($this->assigned_to);
                if (! $assignedUser || $assignedUser->tenant_id !== Auth::user()->tenant_id) {
                    $validator->errors()->add('assigned_to', 'The selected staff member is invalid.');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status selected.',
            'priority.in' => 'Invalid priority selected.',
            'progress.min' => 'Progress must be at least 0.',
            'progress.max' => 'Progress cannot exceed 100.',
            'description.max' => 'Description cannot exceed 5000 characters.',
            'closure_notes.required_if' => 'Closure notes are required when closing a case.',
            'closure_notes.max' => 'Closure notes cannot exceed 2000 characters.',
            'archive_box_number.max' => 'Archive box number cannot exceed 50 characters.',
            'assigned_to.exists' => 'The selected staff member does not exist.',
        ];
    }
}
