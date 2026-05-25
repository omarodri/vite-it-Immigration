<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Dispatcher: routes the update payload to the correct type-specific request
 * based on the *current* client's type (not the incoming payload — type is
 * immutable after creation).
 */
class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.update');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $type = ($client instanceof Client) ? $client->type : 'person';

        if ($type === 'company') {
            $rules = (new UpdateCompanyClientRequest())->setContainer(app())->rules();
        } else {
            $rules = (new UpdatePersonClientRequest())->setContainer(app())->rules();
        }

        // Block type mutation post-creation. The user may send `type` in the
        // payload, but only the existing value is accepted — anything else
        // becomes a 422.
        $rules['type'] = ['sometimes', Rule::in([$type])];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $client = $this->route('client');
        $type = ($client instanceof Client) ? $client->type : 'person';

        if ($type === 'company') {
            return (new UpdateCompanyClientRequest())->setContainer(app())->messages();
        }

        return (new UpdatePersonClientRequest())->setContainer(app())->messages();
    }
}
