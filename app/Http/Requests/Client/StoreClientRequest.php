<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Spec 64 — Clientes Empresa.
 *
 * This request now acts as a dispatcher that delegates to the appropriate
 * type-specific request (Person or Company) based on the incoming `type`
 * field. Keeping a single public entry point preserves the existing route
 * and controller signatures while letting the validation rules diverge.
 */
class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.create');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type', 'person');

        if ($type === 'company') {
            return (new StoreCompanyClientRequest())->setContainer(app())->rules();
        }

        return (new StorePersonClientRequest())->setContainer(app())->rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = $this->input('type', 'person');

        if ($type === 'company') {
            return (new StoreCompanyClientRequest())->setContainer(app())->messages();
        }

        return (new StorePersonClientRequest())->setContainer(app())->messages();
    }

    protected function prepareForValidation(): void
    {
        // Mirror the type-specific defaults so dispatched rules see a coherent payload.
        if (! $this->has('type')) {
            $this->merge(['type' => 'person']);
        }

        if (! $this->has('status')) {
            $this->merge(['status' => 'prospect']);
        }
    }
}
