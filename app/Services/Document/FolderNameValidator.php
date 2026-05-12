<?php

namespace App\Services\Document;

use Illuminate\Validation\ValidationException;

final class FolderNameValidator
{
    public static function validate(string $name, string $field = 'name', ?array $config = null): void
    {
        $rules   = $config ?? config('case_folders.validation', []);
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw ValidationException::withMessages([$field => __('validation.folder_name.required')]);
        }

        $min = (int) ($rules['name_min_length'] ?? 1);
        $max = (int) ($rules['name_max_length'] ?? 100);

        if (mb_strlen($trimmed) < $min) {
            throw ValidationException::withMessages([$field => __('validation.folder_name.min', ['min' => $min])]);
        }

        if (mb_strlen($trimmed) > $max) {
            throw ValidationException::withMessages([$field => __('validation.folder_name.max', ['max' => $max])]);
        }

        $forbiddenRegex = $rules['forbidden_chars_regex'] ?? '/[<>:"\/\\\\|?*]/u';
        if (preg_match($forbiddenRegex, $trimmed)) {
            throw ValidationException::withMessages([$field => __('validation.folder_name.invalid_chars')]);
        }

        $leadingDotRegex = $rules['leading_dot_regex'] ?? '/^\.+\s*$/u';
        if (preg_match($leadingDotRegex, $trimmed)) {
            throw ValidationException::withMessages([$field => __('validation.folder_name.leading_dot')]);
        }

        $base     = mb_strtoupper(pathinfo($trimmed, PATHINFO_FILENAME));
        $reserved = $rules['reserved_names'] ?? [];
        if (in_array($base, $reserved, true)) {
            throw ValidationException::withMessages([$field => __('validation.folder_name.reserved')]);
        }
    }

    public static function isValid(string $name, ?array $config = null): bool
    {
        try {
            self::validate($name, 'name', $config);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
