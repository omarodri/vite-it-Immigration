<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Spec 65 — Validates the payload for `PUT /api/tenant/case-code-pattern`.
 *
 * Allowed values:
 *   - case_code_blocks: any non-empty subset of [YY, TT, AAAA, NNNN], distinct, max 4.
 *   - case_code_separator: '-', '_' or '' (D12).
 *   - case_code_include_name: boolean — appended at the end if true (D2).
 *
 * Business rule (D11): NNNN is mandatory, otherwise sequence uniqueness is lost.
 */
class UpdateCaseCodePatternRequest extends FormRequest
{
    /**
     * The route already enforces the `settings.case_code.manage` permission
     * via middleware. Returning true here keeps the request portable in case
     * the route is mounted under a different guard in the future.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'case_code_blocks'       => ['required', 'array', 'min:1', 'max:4'],
            'case_code_blocks.*'     => ['string', 'in:YY,TT,AAAA,NNNN', 'distinct'],
            'case_code_separator'    => ['required', 'string', 'in:-,_,'],
            'case_code_include_name' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // D11 — NNNN is mandatory to guarantee uniqueness.
            $blocks = $this->input('case_code_blocks', []);
            if (is_array($blocks) && !in_array('NNNN', $blocks, true)) {
                $v->errors()->add(
                    'case_code_blocks',
                    'El bloque NNNN (consecutivo) es obligatorio para garantizar códigos únicos.'
                );
            }
        });
    }

    /**
     * Override default `boolean()` to also accept the string "0"/"1" pattern.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('case_code_include_name')) {
            $this->merge([
                'case_code_include_name' => filter_var(
                    $this->input('case_code_include_name'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }
}
