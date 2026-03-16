<?php

namespace App\Helpers;

class CaseCodeHelper
{
    /**
     * Normalize a client last name to a 4-character uppercase ASCII slug.
     *
     * Pipeline (strict order):
     *   1. Transliterate accents/diacritics → ASCII  (é→e, ü→u, ñ→n, etc.)
     *   2. Uppercase
     *   3. Strip everything that is not A-Z  (spaces, hyphens, apostrophes, digits)
     *   4. Pad with 'X' if result is shorter than 4 characters
     *   5. Truncate to exactly 4 characters
     *
     * Examples:
     *   Rodriguez   → RODR
     *   Gómez       → GOME
     *   De la Cruz  → DELA
     *   García-Pérez→ GARC
     *   O'Brien     → OBRI
     *   Ng          → NGXX
     *   A           → AXXX
     */
    public static function normalizeLastName(string $lastName): string
    {
        // 1. Transliterate UTF-8 accents/diacritics to closest ASCII equivalents
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lastName);

        // Fallback if iconv is unavailable or returns false
        if ($slug === false || $slug === '') {
            $slug = $lastName;
        }

        // 2. Uppercase
        $slug = strtoupper($slug);

        // 3. Keep only A-Z characters (remove spaces, hyphens, apostrophes, numbers, etc.)
        $slug = preg_replace('/[^A-Z]/', '', $slug) ?? '';

        // 4. Pad with 'X' to reach minimum 4 characters
        $slug = str_pad($slug, 4, 'X');

        // 5. Truncate to exactly 4 characters
        return substr($slug, 0, 4);
    }
}
