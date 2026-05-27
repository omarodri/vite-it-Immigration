<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloneTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by TaskTemplatePolicy::clone() in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'target_stage_id' => [
                'required',
                'integer',
                Rule::exists('workflow_stages', 'id')
                    ->where('tenant_id', app('tenant')->id),
            ],
        ];
    }
}
