<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommunityMediaService;
use App\Services\CommunityNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class V123ProfileRuntimeDependencyTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_profile_runtime_services_resolve_and_notification_is_tenant_scoped(): void
    {
        $tenant = $this->tenant();
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id]);

        $notifier = app(CommunityNotifier::class);
        $notification = $notifier->notify($user->id, $tenant->id, 'connection', [
            'title' => 'Partner invitation',
            'url' => '/profile',
        ]);

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('platform_notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => 'connection',
        ]);
    }

    public function test_profile_media_service_uses_private_storage_and_server_derived_extension(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $user = $this->user();
        $upload = $this->pngUpload('avatar.php');

        $stored = app(CommunityMediaService::class)->store($upload, $tenant->id, $user->id, 'profiles');

        $this->assertSame('image', $stored['type']);
        $this->assertSame('image/png', $stored['mime']);
        $this->assertStringEndsWith('.png', $stored['path']);
        $this->assertStringNotContainsString('.php', $stored['path']);
        $this->assertStringStartsWith('tenant/'.$tenant->id.'/community/'.$user->id.'/profiles/', $stored['path']);
        Storage::disk('local')->assertExists($stored['path']);
    }

    public function test_disabled_in_app_connection_preference_suppresses_notification(): void
    {
        $tenant = $this->tenant();
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'in_app_connections' => false]);

        $notification = app(CommunityNotifier::class)->notify($user->id, $tenant->id, 'connection', ['title' => 'Hidden']);

        $this->assertNull($notification);
        $this->assertDatabaseCount('platform_notifications', 0);
    }

    private function pngUpload(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'private-gather-community-png-');
        if ($path === false) {
            $this->fail('Unable to create temporary image fixture.');
        }
        file_put_contents($path, base64_decode(self::PNG_1X1, true));
        return new UploadedFile($path, $clientName, 'image/png', null, true);
    }

    private function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Runtime Dependency Club',
            'slug' => 'runtime-dependency-club-'.bin2hex(random_bytes(3)),
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => [],
        ]);
    }

    private function user(): User
    {
        $suffix = bin2hex(random_bytes(4));
        return User::create([
            'name' => 'Runtime Dependency User',
            'username' => 'runtime-'.$suffix,
            'display_name' => 'runtime-'.$suffix,
            'email' => 'runtime-'.$suffix.'@example.test',
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
