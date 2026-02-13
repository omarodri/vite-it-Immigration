<?php

namespace App\Http\Requests\Case;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AssignCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $this->user()->can('assign', $case);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate user belongs to same tenant
            if ($this->assigned_to) {
                $user = User::find($this->assigned_to);
                if ($user && $user->tenant_id !== Auth::user()->tenant_id) {
                    $validator->errors()->add('assigned_to', 'The selected user is not in your organization.');
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
            'assigned_to.required' => 'A user must be selected for assignment.',
            'assigned_to.exists' => 'The selected user does not exist.',
        ];
    }
}
