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
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_country' => ['nullable', 'string', 'max:100'],
            'passport_expiry_date' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'email'              => ['nullable', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'phone_country_code' => ['nullable', 'string', 'max:6'],
            'canada_status'      => ['nullable', Rule::in(['asylum_seeker', 'refugee', 'temporary_resident', 'permanent_resident', 'citizen', 'visitor', 'student', 'worker', 'other'])],
            'canada_status_other' => ['nullable', 'string', 'max:255', 'required_if:canada_status,other'],
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
