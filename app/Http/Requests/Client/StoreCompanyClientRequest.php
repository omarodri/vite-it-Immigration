<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Validation rules dedicated to company-type client creation. Persona-only
 * fields are explicitly prohibited so the company payload stays clean.
 */
class StoreCompanyClientRequest extends FormRequest
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
            // Discriminator — locked to company for this request.
            'type' => ['required', Rule::in(['company'])],

            // Company Information
            'company_name'    => ['required', 'string', 'max:255'],
            'trade_name'      => ['nullable', 'string', 'max:255'],
            'tax_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->whereNull('deleted_at'),
            ],
            'industry'        => ['nullable', 'string', 'max:100'],
            'website'         => ['nullable', 'url', 'max:255'],
            'legal_rep_name'  => ['nullable', 'string', 'max:255'],
            'legal_rep_title' => ['nullable', 'string', 'max:100'],

            // Shared / common fields
            'description' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', 'string', 'max:10'],

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

            // Status
            'status' => ['nullable', Rule::in(['prospect', 'active', 'inactive', 'archived'])],

            // Persona-only fields are not allowed in a company payload.
            'first_name'         => ['prohibited'],
            'last_name'          => ['prohibited'],
            'date_of_birth'      => ['prohibited'],
            'marital_status'     => ['prohibited'],
            'nationality'        => ['prohibited'],
            'second_nationality' => ['prohibited'],
            'gender'             => ['prohibited'],
            'profession'         => ['prohibited'],
            'canada_status'      => ['prohibited'],
            'status_date'        => ['prohibited'],
            'arrival_date'       => ['prohibited'],
            'entry_point'        => ['prohibited'],
            'iuc'                => ['prohibited'],
            'other_status_1'     => ['prohibited'],
            'other_status_2'     => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'tax_id.required'       => 'Tax ID is required.',
            'tax_id.unique'         => 'A company with this tax ID already exists.',
            'website.url'           => 'Please enter a valid website URL.',
            'email.unique'          => 'A client with this email already exists.',
            'email.email'           => 'Please enter a valid email address.',
            'phone.unique'          => 'A client with this phone number already exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type'   => 'company',
            'status' => $this->has('status') ? $this->status : 'prospect',
        ]);
    }
}
