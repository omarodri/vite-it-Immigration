<?php

namespace App\Http\Requests\Event;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'          => ['sometimes', 'required', 'string', 'max:255'],
            'start_date'     => ['sometimes', 'required', 'date'],
            'end_date'       => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'assigned_to_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)
                                 ->where('is_active', true)
                ),
            ],
            'case_id' => [
                'nullable', 'integer',
                Rule::exists('cases', 'id')->where(
                    fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)
                ),
            ],
            'category'    => ['sometimes', 'required', 'string', Rule::in(Event::$categories)],
            'description' => ['nullable', 'string', 'max:2000'],
            'all_day'     => ['boolean'],
            'location'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
