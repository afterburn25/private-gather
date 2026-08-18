<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_public_upload_ignores_dangerous_client_extension(): void
    {
        Storage::fake('public');
        [$tenant, $domain] = $this->createTenant('media-extension.test');
        $owner = $this->createUser('media-owner@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $file = UploadedFile::fake()->createWithContent('payload.php', base64_decode(self::PNG_1X1, true));

        $this->actingAs($owner)
            ->from('http://'.$domain.'/manage/media')
            ->post('http://'.$domain.'/manage/media', [
                'file' => $file,
                'visibility' => 'public',
                'alt_text' => 'Safe image',
            ])
            ->assertRedirect('http://'.$domain.'/manage/media');

        $asset = MediaAsset::sole();
        $this->assertSame('public', $asset->disk);
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertStringEndsWith('.png', $asset->path);
        $this->assertStringNotContainsString('.php', $asset->path);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_oversized_image_header_is_rejected_and_removed(): void
    {
        Storage::fake('public');
        [$tenant, $domain] = $this->createTenant('media-dimensions.test');
        $owner = $this->createUser('media-size-owner@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $file = UploadedFile::fake()->createWithContent('huge.png', $this->pngHeader(20_000, 20_000));

        $this->actingAs($owner)
            ->from('http://'.$domain.'/manage/media')
            ->post('http://'.$domain.'/manage/media', [
                'file' => $file,
                'visibility' => 'public',
            ])
            ->assertRedirect('http://'.$domain.'/manage/media')
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_private_upload_never_uses_public_disk(): void
    {
        Storage::fake('local');
        [$tenant, $domain] = $this->createTenant('media-private.test');
        $owner = $this->createUser('media-private-owner@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $file = UploadedFile::fake()->createWithContent('private.png', base64_decode(self::PNG_1X1, true));

        $this->actingAs($owner)
            ->from('http://'.$domain.'/manage/media')
            ->post('http://'.$domain.'/manage/media', [
                'file' => $file,
                'visibility' => 'private',
            ])
            ->assertRedirect('http://'.$domain.'/manage/media');

        $asset = MediaAsset::sole();
        $this->assertSame('local', $asset->disk);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_managed_media_response_disables_mime_sniffing(): void
    {
        Storage::fake('local');
        [$tenant, $domain] = $this->createTenant('media-response.test');
        $owner = $this->createUser('media-response-owner@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $path = 'tenant/'.$tenant->id.'/media/test.png';
        Storage::disk('local')->put($path, base64_decode(self::PNG_1X1, true));
        $asset = MediaAsset::create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'size' => Storage::disk('local')->size($path),
            'visibility' => 'private',
            'sha256' => hash('sha256', Storage::disk('local')->get($path)),
            'metadata_stripped' => false,
        ]);

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage/media/'.$asset->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'private,no-store');
    }

    public function test_public_storage_has_executable_file_deny_rule(): void
    {
        $rules = (string) file_get_contents(storage_path('app/public/.htaccess'));
        $this->assertStringContainsString('FilesMatch', $rules);
        $this->assertStringContainsString('phtml', $rules);
        $this->assertStringContainsString('phar', $rules);
        $this->assertStringContainsString('nosniff', $rules);
    }

    private function pngHeader(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $chunk = pack('N', strlen($ihdr)).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
        $iend = pack('N', 0).'IEND'.pack('N', crc32('IEND'));
        return $signature.$chunk.$iend;
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Media Test User',
            'display_name' => 'Media Test User',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Media Test Organization',
            'slug' => str_replace('.', '-', $domain),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        return [$tenant, $domain];
    }
}
