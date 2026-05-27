<?php

namespace App\Services\Case;

use App\Models\CaseType;
use App\Models\Tenant;

/**
 * Spec 65 — Immutable DTO passed to every BlockResolver during case-code generation.
 *
 * Holds all the data resolvers might need without coupling them to the wider
 * service container. The optional `$sequence` is set by the orchestrator via
 * {@see withSequence()} once the next per-tenant counter has been resolved.
 */
final readonly class GenerationContext
{
    public function __construct(
        public Tenant $tenant,
        public CaseType $caseType,
        public string $primaryFullName,
        public string $primaryLastName,
        public ?int $sequence = null,
    ) {}

    public function withSequence(int $sequence): self
    {
        return new self(
            tenant: $this->tenant,
            caseType: $this->caseType,
            primaryFullName: $this->primaryFullName,
            primaryLastName: $this->primaryLastName,
            sequence: $sequence,
        );
    }
}
