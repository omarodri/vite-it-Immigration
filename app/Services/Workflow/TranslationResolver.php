<?php

namespace App\Services\Workflow;

use Illuminate\Database\Eloquent\Model;

class TranslationResolver
{
    public function resolve(Model $model, string $field, string $locale = 'es'): ?string
    {
        if (method_exists($model, 'trans')) {
            return $model->trans($field, $locale);
        }
        return $model->{$field} ?? null;
    }
}
