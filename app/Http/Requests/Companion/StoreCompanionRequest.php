<?php

namespace App\Http\Requests\Companion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'other'])],
            'relationship_other' => ['nullable', 'string', 'max:255', 'required_if:relationship,other'],
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
            'first_name.required' => 'The first name is required.',
            'last_name.required' => 'The last name is required.',
            'relationship.required' => 'The relationship type is required.',
            'relationship.in' => 'The relationship type must be one of: spouse, child, parent, sibling, or other.',
            'relationship_other.required_if' => 'Please specify the relationship when selecting "other".',
            'date_of_birth.before' => 'The date of birth must be a date before today.',
        ];
    }
}
