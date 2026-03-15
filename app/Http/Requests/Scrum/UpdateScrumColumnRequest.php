<?php

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScrumColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
        ];
    }
}
