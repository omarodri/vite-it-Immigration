<?php

namespace App\Models\Traits;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function trans(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $loaded = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        $row = $loaded->first(fn ($t) => $t->locale === $locale && $t->field === $field);
        if ($row) {
            return $row->value;
        }

        if ($locale !== 'es') {
            $fallback = $loaded->first(fn ($t) => $t->locale === 'es' && $t->field === $field);
            if ($fallback) {
                return $fallback->value;
            }
        }

        return null;
    }

    /**
     * @param array<string,string|null> $byLocale
     */
    public function setTranslations(string $field, array $byLocale): void
    {
        foreach ($byLocale as $locale => $value) {
            if ($value === null || $value === '') {
                $this->translations()
                     ->where('locale', $locale)
                     ->where('field', $field)
                     ->delete();
                continue;
            }
            $this->translations()->updateOrCreate(
                ['locale' => $locale, 'field' => $field],
                ['value' => $value, 'tenant_id' => $this->tenant_id ?? null]
            );
        }
    }

    public function translationsByField(): array
    {
        $loaded = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        $out = [];
        foreach ($loaded as $t) {
            $out[$t->field][$t->locale] = $t->value;
        }
        return $out;
    }
}
