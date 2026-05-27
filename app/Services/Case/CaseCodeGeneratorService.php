<?php

namespace App\Services\Case;

use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Services\Case\BlockResolvers\BlockResolverInterface;
use App\Services\Case\BlockResolvers\LastNameBlockResolver;
use App\Services\Case\BlockResolvers\SequenceBlockResolver;
use App\Services\Case\BlockResolvers\TypeBlockResolver;
use App\Services\Case\BlockResolvers\YearBlockResolver;

/**
 * Spec 65 — Orchestrates the generation of a unique case-number.
 *
 * The case_number contains ONLY the 4 configurable blocks (YY, TT, AAAA, NNNN).
 * The applicant's full name is NEVER part of the case_number string — it is
 * used exclusively for the cloud directory path when case_code_include_name=true
 * (handled by FolderService, not here).
 *
 * Race-condition safety: up to 5 retries with sequence++ ensure a free slot
 * or fail loudly so the surrounding transaction rolls back cleanly.
 */
final class CaseCodeGeneratorService
{
    /** @var array<string, BlockResolverInterface> */
    private array $resolvers;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private CaseCodePatternResolver $patternResolver,
        private CaseRepositoryInterface $caseRepository,
        YearBlockResolver $year,
        TypeBlockResolver $type,
        LastNameBlockResolver $lastName,
        SequenceBlockResolver $sequence,
    ) {
        $this->resolvers = [
            'YY'   => $year,
            'TT'   => $type,
            'AAAA' => $lastName,
            'NNNN' => $sequence,
        ];
    }

    /**
     * Generate a unique case-number for the given generation context.
     *
     * @return array{0: string, 1: int}  Tuple of [caseNumber, sequence].
     *
     * @throws \RuntimeException When 5 retries cannot find a free slot.
     */
    public function generate(GenerationContext $ctx): array
    {
        $pattern  = $this->patternResolver->forTenant($ctx->tenant);
        $sequence = $this->caseRepository->getNextSequence($ctx->tenant->id);

        $attempts = 0;
        do {
            $ctxWithSeq = $ctx->withSequence($sequence);
            $caseNumber = $this->buildCode($pattern, $ctxWithSeq);

            if (!$this->caseRepository->existsByCaseNumber($caseNumber)) {
                return [$caseNumber, $sequence];
            }

            $sequence++;
            $attempts++;
        } while ($attempts < self::MAX_ATTEMPTS);

        throw new \RuntimeException(
            "No se pudo generar un código único para el expediente después de {$attempts} intentos."
        );
    }

    private function buildCode(CaseCodePattern $pattern, GenerationContext $ctx): string
    {
        $parts = array_map(
            fn (string $key) => isset($this->resolvers[$key])
                ? $this->resolvers[$key]->resolve($ctx)
                : '',
            $pattern->blocks
        );

        return implode($pattern->separator, $parts);
    }
}
