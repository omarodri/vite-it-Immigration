<?php

namespace App\Http\Requests\Scrum;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScrumTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'case_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->assigned_to_id) {
                $user = User::find($this->assigned_to_id);
                if (! $user || ! $user->hasAnyRole(['consultor', 'apoyo'])) {
                    $validator->errors()->add(
                        'assigned_to_id',
                        'El usuario debe ser un consultor o apoyo.'
                    );
                }
            }
        });
    }
}
