<?php

namespace App\Http\Requests\Companion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateCompanionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'relationship' => ['sometimes', Rule::in(['spouse', 'child', 'parent', 'sibling', 'common-law partner', 'dependent child', 'grandchild', 'grandparent', 'half-sibling', 'step-sibling', 'aunt / uncle', 'niece / nephew', 'cousin', 'child-in-law', 'parent-in-law', 'other'])],
            'relationship_other' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'common_law', 'separated', 'legally_separated', 'annulled_marriage', 'unknown'])],
            'nationality' =>['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'email'              => ['nullable', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'phone_country_code' => ['nullable', 'string', 'max:6'],
            'canada_status'      => ['nullable', Rule::in(['asylum_seeker', 'refugee', 'protected_person', 'temporary_resident', 'permanent_resident', 'citizen', 'visitor', 'student', 'worker', 'other'])],
            'canada_status_other' => ['nullable', 'string', 'max:255', 'required_if:canada_status,other'],
            'arrival_date'        =>['nullable', 'date', 'before_or_equal:today'],
            'iuc' => [
                'nullable',
                'string',
                'max:20',
                Rule::when(
                    fn ($input) => ! empty($input->iuc),
                    [Rule::unique('companions', 'iuc')
                        ->where(fn ($q) => $q->whereIn('client_id',
                            DB::table('clients')
                                ->where('tenant_id', auth()->user()->tenant_id)
                                ->select('id')
                        ))
                        ->ignore($this->route('companion'))]
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->canada_status !== 'other') {
            $this->merge(['canada_status_other' => null]);
        }
        if ($this->canada_status_other) {
            $this->merge(['canada_status_other' => ucfirst(trim($this->canada_status_other))]);
        }
    }

    public function messages(): array
    {
        return [
            'relationship.in' => 'Invalid relationship type.',
            'date_of_birth.before' => 'The date of birth must be a date before today.',
        ];
    }
}
