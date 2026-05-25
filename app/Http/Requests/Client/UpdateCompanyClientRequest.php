<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Partial-update rules for company-type clients. Persona-only fields are
 * prohibited so a company row cannot drift into persona territory.
 */
class UpdateCompanyClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.update');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $clientId = $this->route('client');

        if (is_object($clientId) && method_exists($clientId, 'getKey')) {
            $clientId = $clientId->getKey();
        }

        return [
            // Company Information
            'company_name'    => ['sometimes', 'string', 'max:255'],
            'trade_name'      => ['nullable', 'string', 'max:255'],
            'tax_id' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->whereNull('deleted_at')->ignore($clientId),
            ],
            'industry'        => ['nullable', 'string', 'max:100'],
            'website'         => ['nullable', 'url', 'max:255'],
            'legal_rep_name'  => ['nullable', 'string', 'max:255'],
            'legal_rep_title' => ['nullable', 'string', 'max:100'],

            // Shared
            'description' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', 'string', 'max:10'],

            // Contact Information
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->whereNull('deleted_at')->ignore($clientId),
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
                })->whereNull('deleted_at')->ignore($clientId),
            ],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'phone_country_code'           => ['nullable', 'string', 'max:6'],
            'secondary_phone_country_code' => ['nullable', 'string', 'max:6'],

            // Status
            'status' => ['sometimes', Rule::in(['prospect', 'active', 'inactive', 'archived'])],

            // Persona-only fields are not allowed when updating a company.
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
            'tax_id.unique' => 'A company with this tax ID already exists.',
            'website.url'   => 'Please enter a valid website URL.',
            'email.unique'  => 'A client with this email already exists.',
            'email.email'   => 'Please enter a valid email address.',
            'phone.unique'  => 'A client with this phone number already exists.',
        ];
    }
}
