<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CaseCodeHelper;
use PHPUnit\Framework\TestCase;

class CaseCodeHelperTest extends TestCase
{
    /**
     * @dataProvider lastNameProvider
     */
    public function test_normalize_last_name(string $input, string $expected): void
    {
        $this->assertSame($expected, CaseCodeHelper::normalizeLastName($input));
    }

    public static function lastNameProvider(): array
    {
        return [
            'normal 10 chars'           => ['Rodriguez',     'RODR'],
            'normal lowercase'          => ['rodriguez',     'RODR'],
            'acento é'                  => ['Gómez',         'GOME'],
            'compuesto con espacio'     => ['De la Cruz',    'DELA'],
            'con guión'                 => ['García-Pérez',  'GARC'],
            'con apóstrofe'             => ["O'Brien",       'OBRI'],
            'corto 2 chars'             => ['Ng',            'NGXX'],
            'corto 1 char'              => ['A',             'AXXX'],
            'umlaut alemán ü'           => ['Müller',        'MULL'],
            'exactamente 4 chars'       => ['Cruz',          'CRUZ'],
            'exactamente 5 chars'       => ['Smith',         'SMIT'],
            'ñ español'                 => ['Nuñez',         'NUNE'],
            'acento à francés'          => ['Lefèvre',       'LEFE'],
            'todo mayúsculas'           => ['RODRIGUEZ',     'RODR'],
        ];
    }

    public function test_result_is_always_exactly_4_characters(): void
    {
        $inputs = ['X', 'Li', 'Ng', 'Cruz', 'Smith', 'Rodriguez', 'Vandenberghe'];

        foreach ($inputs as $input) {
            $result = CaseCodeHelper::normalizeLastName($input);
            $this->assertSame(
                4,
                strlen($result),
                "Expected exactly 4 characters for input '{$input}', got '{$result}'"
            );
        }
    }

    public function test_result_contains_only_uppercase_ascii_letters(): void
    {
        $inputs = ['García-Pérez', "O'Brien", 'De la Cruz', 'Müller', 'Nuñez'];

        foreach ($inputs as $input) {
            $slug = CaseCodeHelper::normalizeLastName($input);
            $this->assertMatchesRegularExpression(
                '/^[A-Z]{4}$/',
                $slug,
                "Expected only A-Z characters for input '{$input}', got '{$slug}'"
            );
        }
    }

    public function test_empty_string_returns_four_x_characters(): void
    {
        $this->assertSame('XXXX', CaseCodeHelper::normalizeLastName(''));
    }

    public function test_string_with_only_special_chars_returns_four_x(): void
    {
        // After stripping non-alpha, nothing remains → pad with XXXX
        $this->assertSame('XXXX', CaseCodeHelper::normalizeLastName('123-456'));
    }
}
