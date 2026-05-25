<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Validation rules dedicated to persona-type client creation. Company fields
 * are explicitly prohibited so the persona payload cannot smuggle them in.
 */
class StorePersonClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.create');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            // Discriminator — locked to persona for this request.
            'type' => ['sometimes', Rule::in(['person'])],

            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'second_nationality' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10'],
            'second_language' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'common_law', 'separated', 'legally_separated', 'annulled_marriage', 'unknown'])],
            'profession' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],

            // Contact Information
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->whereNull('deleted_at'),
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
                })->whereNull('deleted_at'),
            ],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'phone_country_code'           => ['nullable', 'string', 'max:6'],
            'secondary_phone_country_code' => ['nullable', 'string', 'max:6'],

            // Legal Status in Canada
            'canada_status' => ['nullable', Rule::in([
                'asylum_seeker', 'refugee', 'protected_person', 'temporary_resident',
                'permanent_resident', 'citizen', 'visitor', 'student', 'worker', 'other',
            ])],
            'status_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
            'entry_point' => ['nullable', Rule::in(['airport', 'land_border', 'green_path'])],
            'iuc' => ['nullable', 'string', 'max:50'],
            'other_status_1' => ['nullable', 'string', 'max:255'],
            'other_status_2' => ['nullable', 'string', 'max:255'],

            // Status
            'status' => ['nullable', Rule::in(['prospect', 'active', 'inactive', 'archived'])],

            // Company fields are not allowed in a persona payload.
            'company_name'    => ['prohibited'],
            'trade_name'      => ['prohibited'],
            'tax_id'          => ['prohibited'],
            'industry'        => ['prohibited'],
            'website'         => ['prohibited'],
            'legal_rep_name'  => ['prohibited'],
            'legal_rep_title' => ['prohibited'],
        ];
    }

    /**
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

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => 'prospect']);
        }
        if (! $this->has('type')) {
            $this->merge(['type' => 'person']);
        }
    }
}
