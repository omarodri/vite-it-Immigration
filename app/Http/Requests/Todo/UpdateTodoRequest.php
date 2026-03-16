<?php

namespace App\Http\Requests\Todo;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'assigned_to_id'  => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)
                                 ->where('is_active', true)
                ),
            ],
            'case_id'         => [
                'nullable', 'integer',
                Rule::exists('cases', 'id')->where(
                    fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)
                                 ->where('status', '!=', 'closed')
                ),
            ],
            'tag'             => ['nullable', 'string', Rule::in(['archivar', 'documentos', 'seguimiento', 'ircc', 'contabilidad'])],
            'priority'        => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'due_date'        => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->assigned_to_id) {
                $user = User::find($this->assigned_to_id);
                if (!$user || !$user->hasAnyRole(['consultor', 'apoyo'])) {
                    $v->errors()->add(
                        'assigned_to_id',
                        'El asignado debe tener rol consultor o apoyo.'
                    );
                }
            }
        });
    }
}
