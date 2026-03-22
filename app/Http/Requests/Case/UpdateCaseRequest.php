<?php

namespace App\Http\Requests\Case;

use App\Models\Companion;
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
            'archive_box_number' => ['nullable', 'string', 'max:50'],
            'important_dates' => ['sometimes', 'array', 'max:20'],
            'important_dates.*.label' => ['required_with:important_dates', 'string', 'max:100'],
            'important_dates.*.due_date' => ['nullable', 'date'],
            'important_dates.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'closure_notes' => ['nullable', 'string', 'max:2000', 'required_if:status,closed'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'companion_ids' => ['sometimes', 'array'],
            'companion_ids.*' => ['integer', 'exists:companions,id'],
            'case_tasks' => ['sometimes', 'array', 'max:50'],
            'case_tasks.*.label' => ['required_with:case_tasks', 'string', 'max:150'],
            'case_tasks.*.is_completed' => ['sometimes', 'boolean'],
            'case_tasks.*.is_custom' => ['sometimes', 'boolean'],
            'case_tasks.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'stage' => ['sometimes', 'nullable', Rule::in(array_keys(ImmigrationCase::STAGE_LABELS))],
            'ircc_status' => ['sometimes', 'nullable', Rule::in(array_keys(ImmigrationCase::IRCC_STATUS_LABELS))],
            'final_result' => ['nullable', Rule::in([
                ImmigrationCase::FINAL_RESULT_APPROVED,
                ImmigrationCase::FINAL_RESULT_DENIED,
            ])],
            'ircc_code' => ['nullable', 'string', 'max:50'],
            'contract_number' => ['nullable', 'string', 'max:50'],
            'service_type' => ['sometimes', Rule::in([
                ImmigrationCase::SERVICE_TYPE_PRO_BONO,
                ImmigrationCase::SERVICE_TYPE_FEE_BASED,
            ])],
            'fees' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        // Strip fees if user lacks permission
        if ($this->has('fees') && ! $this->user()->can('cases.view-fees')) {
            $this->request->remove('fees');
        }

        $validator->after(function ($validator) {
            // Validate assigned_to with grandfather clause
            if ($this->has('assigned_to')) {
                $case = $this->route('case');
                $currentAssignedTo = $case->assigned_to;
                $newAssignedTo = $this->assigned_to;

                // Desasignacion -- always allowed
                if ($newAssignedTo === null) {
                    // no validation needed
                }
                // Grandfather clause -- same value, preserve historical assignment
                elseif ((int) $newAssignedTo === (int) $currentAssignedTo) {
                    // no validation needed
                }
                // New assignment -- full validation
                else {
                    $assignedUser = User::withoutGlobalScopes()->find($newAssignedTo);
                    if (! $assignedUser || $assignedUser->tenant_id !== Auth::user()->tenant_id) {
                        $validator->errors()->add('assigned_to', 'The selected staff member is invalid.');
                    } elseif (! $assignedUser->hasRole('consultor')) {
                        $validator->errors()->add('assigned_to', 'New assignments must be to users with the consultor role.');
                    } elseif (! $assignedUser->is_active) {
                        $validator->errors()->add('assigned_to', 'New assignments must be to active users.');
                    }
                }
            }

            if (! empty($this->companion_ids) && $this->route('case')) {
                $clientId = $this->route('case')?->client_id;

                $validIds = Companion::withoutGlobalScopes()
                    ->where('client_id', $clientId)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereIn('id', $this->companion_ids)
                    ->pluck('id')
                    ->toArray();

                $invalidIds = array_diff($this->companion_ids, $validIds);

                if (! empty($invalidIds)) {
                    $validator->errors()->add('companion_ids', 'One or more selected companions do not belong to this client.');
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
            'important_dates.max' => 'A case cannot have more than 20 important dates.',
            'important_dates.*.label.required_with' => 'Each date must have a label.',
            'important_dates.*.label.max' => 'Date label cannot exceed 100 characters.',
            'important_dates.*.due_date.date' => 'Invalid date format.',
            'assigned_to.exists' => 'The selected staff member does not exist.',
            'companion_ids.array' => 'Companions must be provided as a list.',
            'companion_ids.*.exists' => 'One or more selected companions do not exist.',
            'case_tasks.*.label.required_with' => 'Each task must have a label.',
            'case_tasks.*.label.max' => 'Task label cannot exceed 150 characters.',
            'assigned_to.consultor_role' => 'Solo se pueden asignar consultores a los expedientes.',
            'assigned_to.active_status' => 'El consultor asignado debe estar activo.',
            'stage.in' => 'Invalid stage selected.',
            'ircc_status.in' => 'Invalid IRCC status selected.',
            'final_result.in' => 'Invalid final result selected.',
            'ircc_code.max' => 'IRCC code cannot exceed 50 characters.',
            'service_type.in' => 'Invalid service type selected.',
            'fees.numeric' => 'Fees must be a valid number.',
            'fees.min' => 'Fees cannot be negative.',
            'fees.max' => 'Fees cannot exceed 999,999.99.',
            'contract_number.max' => 'Contract number cannot exceed 50 characters.',
        ];
    }
}
