<?php

namespace Tests\Unit;

use App\Support\DomainName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainNameTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_it_normalizes_domains(string $input, string $expected): void
    {
        $this->assertSame($expected, DomainName::normalize($input));
    }

    public static function normalizationCases(): array
    {
        return [
            ['Example.COM', 'example.com'],
            ['https://Club.Example.com/path', 'club.example.com'],
            ['club.example.com:443', 'club.example.com'],
            ['club.example.com.', 'club.example.com'],
        ];
    }

    public function test_it_rejects_invalid_domains(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainName::normalize('not a domain');
    }

    public function test_it_builds_platform_subdomains(): void
    {
        $this->assertSame('sunset.example.com', DomainName::platformSubdomain('sunset', 'example.com'));
    }
}
