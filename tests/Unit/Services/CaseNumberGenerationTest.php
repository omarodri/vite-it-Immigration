<?php

namespace Tests\Unit\Services;

use App\Helpers\CaseCodeHelper;
use PHPUnit\Framework\TestCase;

/**
 * Validates the complete case-number format:
 *   {YY}-{TYPE_CODE}-{LAST4}-{SEQUENCE4}
 *
 * These tests exercise the sprintf template used in CaseService::generateCaseNumber()
 * without needing the database or any framework bootstrapping.
 */
class CaseNumberGenerationTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Canonical example from the spec
    // ---------------------------------------------------------------------------

    /**
     * The primary business requirement from spec/20:
     *   2026 + Residencia Temporal (RT) + Rodriguez + sequence 60 = "26-RT-RODR-0060"
     */
    public function test_canonical_example_from_spec(): void
    {
        $year2        = '26';
        $typeCode     = 'RT';
        $lastNameSlug = CaseCodeHelper::normalizeLastName('Rodriguez');
        $sequence     = 60;

        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $typeCode, $lastNameSlug, $sequence);

        $this->assertSame('26-RT-RODR-0060', $caseNumber);
    }

    // ---------------------------------------------------------------------------
    // Parametrized format tests
    // ---------------------------------------------------------------------------

    /**
     * @dataProvider caseNumberFormatProvider
     */
    public function test_format_matches_expected(
        string $year2,
        string $typeCode,
        string $lastName,
        int    $sequence,
        string $expected
    ): void {
        $slug       = CaseCodeHelper::normalizeLastName($lastName);
        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $typeCode, $slug, $sequence);

        $this->assertSame($expected, $caseNumber);
    }

    public static function caseNumberFormatProvider(): array
    {
        return [
            'caso canónico del spec'        => ['26', 'RT',                  'Rodriguez',  60,   '26-RT-RODR-0060'],
            'primer expediente del año'     => ['26', 'EXPRESS_ENTRY',       'Smith',      1,    '26-EXPRESS_ENTRY-SMIT-0001'],
            'apellido con acento'           => ['26', 'STUDENT',             'Gómez',      5,    '26-STUDENT-GOME-0005'],
            'apellido corto 2 chars'        => ['26', 'ASYLUM',              'Ng',         3,    '26-ASYLUM-NGXX-0003'],
            'apellido corto 1 char'         => ['26', 'WORK',                'A',          1,    '26-WORK-AXXX-0001'],
            'secuencia máxima 4 dígitos'    => ['26', 'WORK',                'Brown',      9999, '26-WORK-BROW-9999'],
            'apellido compuesto con espacio'=> ['26', 'SPONSORSHIP',         'De la Cruz', 12,   '26-SPONSORSHIP-DELA-0012'],
            'apellido con guión'            => ['26', 'CITIZENSHIP',         'García-Pérez', 7,  '26-CITIZENSHIP-GARC-0007'],
            'año diferente'                 => ['25', 'RT',                  'Rodriguez',  1,    '25-RT-RODR-0001'],
        ];
    }

    // ---------------------------------------------------------------------------
    // Structural invariants
    // ---------------------------------------------------------------------------

    public function test_sequence_padding_is_always_4_digits(): void
    {
        foreach ([1, 9, 10, 99, 100, 999] as $seq) {
            $caseNumber = sprintf('%s-%s-%s-%04d', '26', 'RT', 'RODR', $seq);
            $parts      = explode('-', $caseNumber);
            $sequencePart = end($parts);

            $this->assertSame(4, strlen($sequencePart), "Sequence '{$seq}' should be zero-padded to 4 digits");
        }
    }

    public function test_year_segment_is_2_digits(): void
    {
        $year2 = date('y');
        $this->assertSame(2, strlen($year2));
        $this->assertMatchesRegularExpression('/^\d{2}$/', $year2);
    }

    public function test_last_name_slug_is_always_4_uppercase_letters(): void
    {
        $testCases = ['Rodriguez', 'Ng', 'Smith', 'Gómez', 'De la Cruz'];

        foreach ($testCases as $lastName) {
            $slug = CaseCodeHelper::normalizeLastName($lastName);
            $this->assertSame(4, strlen($slug), "Slug for '{$lastName}' must be 4 chars");
            $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $slug, "Slug for '{$lastName}' must be A-Z only");
        }
    }

    public function test_full_format_structure(): void
    {
        $caseNumber = '26-RT-RODR-0060';
        $parts      = explode('-', $caseNumber);

        // The format has exactly 4 segments separated by '-'
        // Note: type codes like EXPRESS_ENTRY also contain underscores but no hyphens
        $this->assertCount(4, $parts);
        $this->assertSame('26',   $parts[0]);   // YY
        $this->assertSame('RT',   $parts[1]);   // TYPE_CODE
        $this->assertSame('RODR', $parts[2]);   // LAST4
        $this->assertSame('0060', $parts[3]);   // SEQUENCE4
    }
}
