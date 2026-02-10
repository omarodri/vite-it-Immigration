<?php

namespace App\Http\Requests\Companion;

use Illuminate\Foundation\Http\FormRequest;
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
            'relationship' => ['sometimes', Rule::in(['spouse', 'child', 'parent', 'sibling', 'other'])],
            'relationship_other' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_country' => ['nullable', 'string', 'max:100'],
            'passport_expiry_date' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'relationship.in' => 'The relationship type must be one of: spouse, child, parent, sibling, or other.',
            'date_of_birth.before' => 'The date of birth must be a date before today.',
        ];
    }
}
