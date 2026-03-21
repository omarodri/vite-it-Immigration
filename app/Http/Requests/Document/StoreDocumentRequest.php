<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('documents.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200', // 50MB
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv,zip',
            ],
            'folder_id' => [
                'nullable',
                'integer',
                'exists:document_folders,id',
            ],
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
            'file.required' => 'A file must be uploaded.',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file size cannot exceed 50MB.',
            'file.mimes' => 'Allowed file types: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, TXT, CSV, ZIP.',
            'folder_id.exists' => 'The selected folder does not exist.',
            'category.in' => 'Invalid category selected.',
        ];
    }
}
