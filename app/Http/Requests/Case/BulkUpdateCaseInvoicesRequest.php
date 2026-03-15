<?php

namespace App\Http\Requests\Case;

use App\Models\CaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkUpdateCaseInvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('cases.manage-invoices');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoices' => ['present', 'array', 'max:100'],
            'invoices.*.invoice_number' => ['required', 'string', 'max:50'],
            'invoices.*.invoice_date' => ['required', 'date', 'date_format:Y-m-d'],
            'invoices.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'invoices.*.is_collected' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $invoices = $this->input('invoices', []);

            if (! is_array($invoices)) {
                return;
            }

            // Check for duplicate invoice_numbers within the array
            $numbers = [];
            foreach ($invoices as $idx => $invoice) {
                $number = $invoice['invoice_number'] ?? null;
                if ($number === null) {
                    continue;
                }

                if (in_array($number, $numbers, true)) {
                    $validator->errors()->add(
                        "invoices.{$idx}.invoice_number",
                        'The invoice number has already been taken.'
                    );
                }
                $numbers[] = $number;
            }

            // Check uniqueness per tenant (excluding current case)
            $case = $this->route('case');
            $tenantId = $this->user()->tenant_id;

            foreach ($invoices as $idx => $invoice) {
                $number = $invoice['invoice_number'] ?? null;
                if ($number === null) {
                    continue;
                }

                $exists = CaseInvoice::whereHas('case', function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })
                    ->where('invoice_number', $number)
                    ->where('case_id', '!=', $case->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        "invoices.{$idx}.invoice_number",
                        'The invoice number has already been taken.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoices.present' => 'Invoice list is required.',
            'invoices.*.invoice_number.required' => 'Each invoice must have a number.',
            'invoices.*.invoice_number.max' => 'Invoice number cannot exceed 50 characters.',
            'invoices.*.invoice_date.required' => 'Each invoice must have a date.',
            'invoices.*.invoice_date.date_format' => 'Invoice date must be in Y-m-d format.',
            'invoices.*.amount.required' => 'Each invoice must have an amount.',
            'invoices.*.amount.min' => 'The amount must be at least 0.',
            'invoices.*.amount.max' => 'The amount must not exceed 99,999,999.99.',
        ];
    }
}
