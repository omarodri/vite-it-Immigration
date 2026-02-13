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
            'hearing_date' => ['nullable', 'date', 'after_or_equal:today'],
            'fda_deadline' => ['nullable', 'date'],
            'brown_sheet_date' => ['nullable', 'date'],
            'evidence_deadline' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'companion_ids' => ['nullable', 'array'],
            'companion_ids.*' => ['integer', 'exists:companions,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
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

            // Validate assigned user belongs to user's tenant
            if ($this->assigned_to) {
                $assignedUser = User::withoutGlobalScopes()->find($this->assigned_to);
                if (! $assignedUser || $assignedUser->tenant_id !== Auth::user()->tenant_id) {
                    $validator->errors()->add('assigned_to', 'The selected staff member is invalid.');
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
            'hearing_date.after_or_equal' => 'Hearing date must be today or in the future.',
            'description.max' => 'Description cannot exceed 5000 characters.',
            'assigned_to.exists' => 'The selected staff member does not exist.',
            'companion_ids.array' => 'Companions must be provided as a list.',
            'companion_ids.*.exists' => 'One or more selected companions do not exist.',
        ];
    }
}
