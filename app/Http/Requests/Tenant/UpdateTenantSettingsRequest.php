<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be an admin to update tenant settings
        return $this->user()->can('settings.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'company_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'company_website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'date_format' => ['sometimes', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d-m-Y'],
            'language' => ['sometimes', 'string', 'in:es,en,fr'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_email.email' => 'The company email must be a valid email address.',
            'company_website.url' => 'The company website must be a valid URL.',
            'timezone.timezone' => 'The timezone must be a valid timezone.',
            'language.in' => 'The language must be one of: Spanish (es), English (en), French (fr).',
        ];
    }
}
