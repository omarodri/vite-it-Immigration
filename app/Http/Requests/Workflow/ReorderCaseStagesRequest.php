<?php

namespace App\Http\Requests\Workflow;

use App\Models\CaseStage;
use App\Models\ImmigrationCase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCaseStagesRequest extends FormRequest
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
            'stage_ids'   => ['required', 'array', 'min:1'],
            'stage_ids.*' => [
                'integer',
                Rule::exists('case_stages', 'id')
                    ->where('case_id', $caseId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            /** @var ImmigrationCase|null $case */
            $case = $this->route('case');
            if (! $case) {
                return;
            }

            $stageIds = (array) $this->input('stage_ids', []);

            $expected = CaseStage::where('case_id', $case->id)
                ->whereNull('deleted_at')
                ->count();

            if (count($stageIds) !== $expected) {
                $v->errors()->add('stage_ids', 'Debe incluir TODAS las etapas del expediente.');
            }
            if (count($stageIds) !== count(array_unique($stageIds))) {
                $v->errors()->add('stage_ids', 'Hay etapas duplicadas en el orden.');
            }
        });
    }
}
