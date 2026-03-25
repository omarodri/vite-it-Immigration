<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clients.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'second_nationality' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10'],
            'second_language' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_country' => ['nullable', 'string', 'max:100'],
            'passport_expiry_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'common_law', 'separated'])],
            'profession' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],

            // Contact Information
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'residential_address' => ['nullable', 'string', 'max:500'],
            'mailing_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'phone_country_code'           => ['nullable', 'string', 'max:6'],
            'secondary_phone_country_code' => ['nullable', 'string', 'max:6'],

            // Legal Status in Canada
            'canada_status' => ['nullable', Rule::in([
                'asylum_seeker', 'refugee', 'temporary_resident', 'permanent_resident',
                'citizen', 'visitor', 'student', 'worker', 'other',
            ])],
            'status_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
            'entry_point' => ['nullable', Rule::in(['airport', 'land_border', 'green_path'])],
            'iuc' => ['nullable', 'string', 'max:50'],
            'work_permit_number' => ['nullable', 'string', 'max:50'],
            'study_permit_number' => ['nullable', 'string', 'max:50'],
            'permit_expiry_date' => ['nullable', 'date'],
            'other_status_1' => ['nullable', 'string', 'max:255'],
            'other_status_2' => ['nullable', 'string', 'max:255'],

            // Status
            'status' => ['nullable', Rule::in(['prospect', 'active', 'inactive', 'archived'])],
            'is_primary_applicant' => ['nullable', 'boolean'],
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
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.unique' => 'A client with this email already exists.',
            'email.email' => 'Please enter a valid email address.',
            'phone.unique' => 'A client with this phone number already exists.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status to prospect if not provided
        if (! $this->has('status')) {
            $this->merge(['status' => 'prospect']);
        }

        // Set default is_primary_applicant if not provided
        if (! $this->has('is_primary_applicant')) {
            $this->merge(['is_primary_applicant' => true]);
        }
    }
}
