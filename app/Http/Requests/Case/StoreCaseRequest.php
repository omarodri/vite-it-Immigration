<?php

namespace App\Http\Requests\Case;

use App\Models\CaseType;
use App\Models\Client;
use App\Models\Companion;
use App\Models\ImmigrationCase;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('cases.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'case_type_id' => ['required', 'integer', 'exists:case_types,id'],
            'priority' => ['sometimes', Rule::in([
                ImmigrationCase::PRIORITY_URGENT,
                ImmigrationCase::PRIORITY_HIGH,
                ImmigrationCase::PRIORITY_MEDIUM,
                ImmigrationCase::PRIORITY_LOW,
            ])],
            'language' => ['sometimes', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'important_dates' => ['sometimes', 'array', 'max:20'],
            'important_dates.*.label' => ['required_with:important_dates', 'string', 'max:100'],
            'important_dates.*.due_date' => ['nullable', 'date'],
            'important_dates.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'companion_ids' => ['nullable', 'array'],
            'companion_ids.*' => ['integer', 'exists:companions,id'],
            'case_tasks' => ['sometimes', 'array', 'max:50'],
            'case_tasks.*.label' => ['required_with:case_tasks', 'string', 'max:150'],
            'case_tasks.*.is_completed' => ['sometimes', 'boolean'],
            'case_tasks.*.is_custom' => ['sometimes', 'boolean'],
            'case_tasks.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'service_type' => ['sometimes', Rule::in([
                ImmigrationCase::SERVICE_TYPE_PRO_BONO,
                ImmigrationCase::SERVICE_TYPE_FEE_BASED,
            ])],
            'contract_number' => ['nullable', 'string', 'max:50'],
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
            // Validate client belongs to user's tenant
            // Use withoutGlobalScopes to bypass BelongsToTenant filter
            if ($this->client_id) {
                $client = Client::withoutGlobalScopes()->find($this->client_id);
                if (! $client || $client->tenant_id !== Auth::user()->tenant_id) {
                    $validator->errors()->add('client_id', 'The selected client is invalid.');
                }
            }

            // Validate case type is active
            if ($this->case_type_id) {
                $caseType = CaseType::find($this->case_type_id);
                if ($caseType && ! $caseType->is_active) {
                    $validator->errors()->add('case_type_id', 'The selected case type is not active.');
                }
            }

            // Validate assigned user belongs to user's tenant, has consultor role, and is active
            if ($this->assigned_to) {
                $assignedUser = User::withoutGlobalScopes()->find($this->assigned_to);
                if (! $assignedUser || $assignedUser->tenant_id !== Auth::user()->tenant_id) {
                    $validator->errors()->add('assigned_to', 'The selected staff member is invalid.');
                } elseif (! $assignedUser->hasRole('consultor')) {
                    $validator->errors()->add('assigned_to', 'The assigned user must have the consultor role.');
                } elseif (! $assignedUser->is_active) {
                    $validator->errors()->add('assigned_to', 'The assigned user must be active.');
                }
            }

            // Validate companions belong to the selected client
            if ($this->client_id && ! empty($this->companion_ids)) {
                $validCompanionIds = Companion::withoutGlobalScopes()
                    ->where('client_id', $this->client_id)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereIn('id', $this->companion_ids)
                    ->pluck('id')
                    ->toArray();

                $invalidIds = array_diff($this->companion_ids, $validCompanionIds);
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
            'client_id.required' => 'A client must be selected.',
            'client_id.exists' => 'The selected client does not exist.',
            'case_type_id.required' => 'A case type must be selected.',
            'case_type_id.exists' => 'The selected case type does not exist.',
            'priority.in' => 'Invalid priority selected.',
            'description.max' => 'Description cannot exceed 5000 characters.',
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
            'service_type.in' => 'Invalid service type selected.',
            'fees.numeric' => 'Fees must be a valid number.',
            'fees.min' => 'Fees cannot be negative.',
            'fees.max' => 'Fees cannot exceed 999,999.99.',
            'contract_number.max' => 'Contract number cannot exceed 50 characters.',
        ];
    }
}
