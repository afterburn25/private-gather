<?php

namespace Tests\Unit;

use App\Support\UpgradePath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpgradePathTest extends TestCase
{
    #[DataProvider('validPaths')]
    public function test_valid_paths_are_normalized(string $path, string $expected): void
    {
        self::assertSame($expected, UpgradePath::normalize($path));
    }

    #[DataProvider('invalidPaths')]
    public function test_protected_or_unsafe_paths_are_rejected(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        UpgradePath::normalize($path);
    }

    public static function validPaths(): array
    {
        return [
            ['VERSION', 'VERSION'],
            ['app\\Example.php', 'app/Example.php'],
            ['database/migrations/test.php', 'database/migrations/test.php'],
        ];
    }

    public static function invalidPaths(): array
    {
        return [
            ['../.env'],
            ['.env'],
            ['storage/app/test'],
            ['install/index.php'],
            ['public/uploads/file.jpg'],
            ['C:/windows/test'],
        ];
    }
}
