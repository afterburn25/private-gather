<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    #[DataProvider('dangerousValues')]
    public function test_formula_like_values_are_forced_to_text(string $value): void
    {
        self::assertSame("'".$value, CsvCell::safe($value));
    }

    public function test_normal_values_and_numeric_types_are_unchanged(): void
    {
        self::assertSame('Summer Party', CsvCell::safe('Summer Party'));
        self::assertSame('2026-08-19T18:00:00Z', CsvCell::safe('2026-08-19T18:00:00Z'));
        self::assertSame(25, CsvCell::safe(25));
        self::assertNull(CsvCell::safe(null));
    }

    public static function dangerousValues(): array
    {
        return [
            ['=2+2'],
            ['+SUM(A1:A2)'],
            ['-1+2'],
            ['@SUM(A1:A2)'],
            ['   =HYPERLINK("https://example.test")'],
            ["\t=2+2"],
            ["\r@SUM(A1:A2)"],
            ["\nplain-but-control-prefixed"],
        ];
    }
}
