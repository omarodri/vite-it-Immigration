<?php

namespace App\Http\Requests\Workflow;

use App\Models\ImmigrationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCaseStageRequest extends FormRequest
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
        $stage = $this->route('stage');
        $stageId = is_object($stage) ? $stage->id : $stage;

        return [
            'policy' => ['required', 'in:move,trash'],
            'move_to_stage_id' => [
                'required_if:policy,move',
                'nullable',
                'integer',
                Rule::exists('case_stages', 'id')
                    ->where('case_id', $caseId)
                    ->where('id', '!=', $stageId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
