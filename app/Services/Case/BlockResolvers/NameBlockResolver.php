<?php

namespace App\Services\Case\BlockResolvers;

use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Resolves the optional NAME block (full applicant name).
 *
 * Sanitisation pipeline (aligned with FolderNameValidator so the output is
 * always safe to use as part of a cloud-storage path):
 *   1. UTF-8 → ASCII transliteration (acentos → ASCII).
 *   2. Remove characters forbidden by FolderNameValidator: < > : " / \ | ? *
 *   3. Spaces (and runs of whitespace) → single hyphen.
 *   4. Collapse repeated hyphens.
 *   5. Truncate to 60 chars to keep total path length sane on Windows hosts.
 */
final class NameBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return $this->resolveFromString($ctx->primaryFullName);
    }

    /** Sanitize an arbitrary full-name string (usable without a GenerationContext). */
    public function resolveFromString(string $fullName): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fullName);
        if ($ascii === false || $ascii === '') {
            $ascii = $fullName;
        }

        $clean = preg_replace('/[<>:"\/\\\\|?*]/u', '', $ascii) ?? '';
        $clean = preg_replace('/\s+/', '-', trim($clean)) ?? '';
        $clean = preg_replace('/-+/', '-', $clean) ?? '';
        $clean = trim($clean, '-');

        return mb_substr($clean, 0, 60);
    }
}
