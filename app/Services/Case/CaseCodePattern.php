<?php

namespace App\Services\Case;

/**
 * Spec 65 — Value object representing the per-tenant case-code pattern.
 *
 * Defaults match Spec 55: `YY-TT-AAAA-NNNN` with NAME disabled — guaranteeing
 * tenants that never touch the settings panel keep the legacy format.
 */
final readonly class CaseCodePattern
{
    /**
     * @param array<int, string> $blocks  Ordered list of block keys (YY, TT, AAAA, NNNN)
     * @param string             $separator  '-' | '_' | ''
     * @param bool               $includeName  When true, NAME segment is appended at the end
     */
    public function __construct(
        public array $blocks,
        public string $separator,
        public bool $includeName,
    ) {}

    public static function fromSettings(array $settings): self
    {
        return new self(
            blocks: data_get($settings, 'case_code_blocks', ['YY', 'TT', 'AAAA', 'NNNN']),
            separator: (string) data_get($settings, 'case_code_separator', '-'),
            includeName: (bool) data_get($settings, 'case_code_include_name', false),
        );
    }
}
