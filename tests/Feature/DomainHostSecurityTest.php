<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\CustomDomainService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class DomainHostSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://platform.test',
            'platform.root_domain' => 'privategather.com',
            'platform.central_domains' => ['platform.test', 'privategather.com', 'www.privategather.com'],
        ]);
    }

    public function test_active_but_unverified_tenant_domain_never_resolves(): void
    {
        $tenant = $this->createTenant();
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'unverified-tenant.test',
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => null,
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $this->get('http://unverified-tenant.test/')
            ->assertNotFound();
    }

    public function test_cached_domain_id_is_reauthorized_after_domain_is_disabled(): void
    {
        $tenant = $this->createTenant();
        $domain = TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'cached-tenant.test',
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $this->get('http://cached-tenant.test/')
            ->assertOk();

        // Leave the host->ID cache populated, then revoke authorization state.
        $domain->update(['status' => TenantDomain::STATUS_FAILED]);

        $this->get('http://cached-tenant.test/')
            ->assertNotFound();
    }

    public function test_malformed_host_fails_with_bad_request_before_tenant_resolution(): void
    {
        $this->withHeader('Host', 'bad_host')
            ->get('/')
            ->assertBadRequest();
    }

    public function test_custom_domain_api_cannot_claim_private_gather_owned_namespace(): void
    {
        $tenant = $this->createTenant();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('platform domains cannot be claimed');

        app(CustomDomainService::class)->request($tenant, 'support.privategather.com');
    }

    public function test_password_reset_link_is_pinned_to_canonical_app_url_not_tenant_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://platform.test/private-gather']);
        [$tenant, $domain] = $this->createTenantWithDomain('tenant-reset.test');
        $user = $this->createUser('reset-link@example.test');

        $this->post('http://'.$domain.'/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $domain): bool {
            $url = (string) $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://platform.test/private-gather/reset-password/')
                && str_contains($url, 'email=reset-link%40example.test')
                && ! str_contains($url, $domain);
        });
    }

    public function test_email_verification_link_is_canonical_and_relative_signature_validates(): void
    {
        Notification::fake();
        [$tenant, $domain] = $this->createTenantWithDomain('tenant-verify.test');
        $user = $this->createUser('verify-link@example.test');
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->post('http://'.$domain.'/email/verification-notification')
            ->assertRedirect();

        $captured = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, $domain, &$captured): bool {
            $captured = (string) $notification->toMail($user)->actionUrl;

            return str_starts_with($captured, 'https://platform.test/email/verify/')
                && ! str_contains($captured, $domain);
        });

        $this->assertIsString($captured);
        $this->actingAs($user)
            ->get($captured)
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Host Security Tenant',
            'slug' => 'host-security-'.bin2hex(random_bytes(4)),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);
    }

    private function createTenantWithDomain(string $domain): array
    {
        $tenant = $this->createTenant();
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

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Host Security User',
            'display_name' => 'Host Security User',
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
}
