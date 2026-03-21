<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('documents.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'original_name' => ['sometimes', 'string', 'max:255'],
            'category' => [
                'sometimes',
                Rule::in([
                    Document::CATEGORY_ADMISSION,
                    Document::CATEGORY_HISTORY,
                    Document::CATEGORY_EVIDENCE,
                    Document::CATEGORY_HEARING,
                    Document::CATEGORY_CONTRACT,
                    Document::CATEGORY_OTHER,
                ]),
            ],
            'folder_id' => ['nullable', 'integer', 'exists:document_folders,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'original_name.max' => 'The document name cannot exceed 255 characters.',
            'category.in' => 'Invalid category selected.',
            'folder_id.exists' => 'The selected folder does not exist.',
        ];
    }
}
