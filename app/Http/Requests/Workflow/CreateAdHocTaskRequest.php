<?php

namespace App\Http\Requests\Workflow;

use App\Models\ImmigrationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdHocTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ImmigrationCase|null $case */
        $case = $this->route('case');
        $caseId = $case?->id;

        return [
            'case_stage_id' => [
                'required',
                'integer',
                Rule::exists('case_stages', 'id')
                    ->where('case_id', $caseId)
                    ->whereNull('deleted_at'),
            ],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'in:translation,case_creation,accounting,filing,document,other'],
            'priority'    => ['required', 'in:low,medium,high,urgent'],
            'due_date'    => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
