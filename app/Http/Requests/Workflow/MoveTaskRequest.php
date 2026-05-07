<?php

namespace App\Http\Requests\Workflow;

use App\Models\ImmigrationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
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
            'source_stage_id' => [
                'required',
                'integer',
                Rule::exists('case_stages', 'id')
                    ->where('case_id', $caseId)
                    ->whereNull('deleted_at'),
            ],
            'target_stage_id' => [
                'required',
                'integer',
                Rule::exists('case_stages', 'id')
                    ->where('case_id', $caseId)
                    ->whereNull('deleted_at'),
            ],
            'target_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
