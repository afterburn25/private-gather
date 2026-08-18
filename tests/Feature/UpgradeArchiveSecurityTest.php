<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Upgrade\UpgradePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class UpgradeArchiveSecurityTest extends TestCase
{
    use RefreshDatabase;

    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_upgrade_package_streams_normal_payload_and_preserves_checksum(): void
    {
        $content = "<?php\nreturn 'safe';\n";
        $zipPath = $this->makePackage([
            'app/Safe.php' => $content,
        ]);
        $stage = $this->temporaryPath('stage');
        mkdir($stage, 0775, true);

        $package = UpgradePackage::open($zipPath);
        $package->extractTo($stage);

        $this->assertSame($content, file_get_contents($stage.'/app/Safe.php'));
    }

    public function test_single_expanded_payload_file_cannot_exceed_configured_limit(): void
    {
        config([
            'upgrades.max_file_mb' => 1,
            'upgrades.max_expanded_mb' => 8,
        ]);
        $zipPath = $this->makePackage([
            'app/Large.php' => str_repeat('A', (1024 * 1024) + 1),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('per-file size limit');
        UpgradePackage::open($zipPath);
    }

    public function test_total_expanded_payload_cannot_exceed_configured_limit(): void
    {
        config([
            'upgrades.max_file_mb' => 2,
            'upgrades.max_expanded_mb' => 1,
        ]);
        $zipPath = $this->makePackage([
            'app/One.php' => str_repeat('A', 600 * 1024),
            'app/Two.php' => str_repeat('B', 600 * 1024),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expanded payload');
        UpgradePackage::open($zipPath);
    }

    public function test_case_colliding_zip_members_are_rejected_before_manifest_processing(): void
    {
        $zipPath = $this->temporaryPath('collision.zip');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', json_encode([
            'format' => 1,
            'product' => config('upgrades.product'),
            'from_versions' => ['1.0.0'],
            'to_version' => '1.0.1',
            'files' => [[
                'path' => 'app/Foo.php',
                'action' => 'replace',
                'sha256' => hash('sha256', 'one'),
            ]],
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('payload/app/Foo.php', 'one');
        $zip->addFromString('payload/app/foo.php', 'two');
        $zip->close();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('case-colliding');
        UpgradePackage::open($zipPath);
    }

    public function test_invalid_uploaded_zip_is_removed_even_when_package_open_fails_early(): void
    {
        config(['platform.central_domains' => ['platform.test']]);
        $upgradeRoot = $this->temporaryPath('upgrade-root');
        mkdir($upgradeRoot, 0775, true);
        config(['upgrades.storage_path' => $upgradeRoot]);

        $admin = User::create([
            'name' => 'Upgrade Admin',
            'display_name' => 'Upgrade Admin',
            'email' => 'upgrade-admin@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => true,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $this->actingAs($admin)
            ->post('http://platform.test/admin/upgrades', [
                'upgrade' => UploadedFile::fake()->createWithContent('broken.zip', 'this is not a zip archive'),
                'confirm_backup' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('upgrade');

        $incomingFiles = is_dir($upgradeRoot.'/incoming')
            ? array_values(array_filter(glob($upgradeRoot.'/incoming/*') ?: [], 'is_file'))
            : [];
        $this->assertSame([], $incomingFiles);
    }

    private function makePackage(array $files): string
    {
        $manifestFiles = [];
        foreach ($files as $path => $content) {
            $manifestFiles[] = [
                'path' => $path,
                'action' => 'replace',
                'sha256' => hash('sha256', $content),
            ];
        }

        $zipPath = $this->temporaryPath('package.zip');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', json_encode([
            'format' => 1,
            'product' => config('upgrades.product'),
            'from_versions' => ['1.0.0'],
            'to_version' => '1.0.1',
            'files' => $manifestFiles,
            'migrations' => [],
            'minimum_php' => '8.3.0',
        ], JSON_THROW_ON_ERROR));
        foreach ($files as $path => $content) {
            $zip->addFromString('payload/'.$path, $content);
        }
        $zip->close();

        return $zipPath;
    }

    private function temporaryPath(string $suffix): string
    {
        $path = storage_path('framework/testing/private-gather-'.bin2hex(random_bytes(6)).'-'.$suffix);
        $this->cleanup[] = $path;
        return $path;
    }
}
