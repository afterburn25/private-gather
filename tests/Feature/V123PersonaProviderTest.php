<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\Verification\PersonaAgeVerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class V123PersonaProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_begin_creates_inquiry_then_resumes_for_session_token(): void
    {
        config([
            'age_verification.persona.api_key' => 'persona-test-key',
            'age_verification.persona.inquiry_template_id' => 'itmpl_test',
            'age_verification.persona.environment_id' => 'env_test',
            'age_verification.persona.api_base' => 'https://api.withpersona.com/api/v1',
            'age_verification.persona.hosted_base' => 'https://inquiry.withpersona.com/verify',
            'age_verification.persona.api_version' => '2025-10-27',
        ]);

        Http::fake([
            'https://api.withpersona.com/api/v1/inquiries' => Http::response(['data' => ['id' => 'inq_test123']], 201),
            'https://api.withpersona.com/api/v1/inquiries/inq_test123/resume' => Http::response(['meta' => ['session-token' => 'session-secret-token']], 200),
        ]);

        $user = $this->user();
        $result = app(PersonaAgeVerificationProvider::class)->begin($user, 'pgv_opaque_reference', 'https://platform.test/verification/return');

        $this->assertSame('persona', $result['provider']);
        $this->assertSame('inq_test123', $result['reference']);
        $this->assertStringContainsString('inquiry-id=inq_test123', $result['url']);
        $this->assertStringContainsString('session-token=session-secret-token', $result['url']);
        $this->assertStringContainsString('redirect-uri=https%3A%2F%2Fplatform.test%2Fverification%2Freturn', $result['url']);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.withpersona.com/api/v1/inquiries') return false;
            return $request->hasHeader('Persona-Version', '2025-10-27')
                && data_get($request->data(), 'data.attributes.inquiry-template-id') === 'itmpl_test'
                && data_get($request->data(), 'data.attributes.reference-id') === 'pgv_opaque_reference';
        });
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.withpersona.com/api/v1/inquiries/inq_test123/resume');
    }

    public function test_webhook_signature_accepts_rotation_signature_and_rejects_stale_timestamp(): void
    {
        config([
            'age_verification.persona.webhook_secret' => 'new-secret',
            'age_verification.persona.webhook_previous_secret' => 'old-secret',
            'age_verification.webhook_tolerance_seconds' => 300,
        ]);
        $provider = app(PersonaAgeVerificationProvider::class);
        $body = '{"data":{"id":"evt_123"}}';
        $timestamp = time();
        $old = hash_hmac('sha256', $timestamp.'.'.$body, 'old-secret');
        $bogus = str_repeat('0', 64);

        $this->assertTrue($provider->verifyWebhookSignature($body, "t={$timestamp},v1={$bogus} t={$timestamp},v1={$old}"));

        $stale = $timestamp - 3600;
        $staleSig = hash_hmac('sha256', $stale.'.'.$body, 'new-secret');
        $this->assertFalse($provider->verifyWebhookSignature($body, "t={$stale},v1={$staleSig}"));
    }

    private function user(): User
    {
        $user = User::create([
            'name' => 'Private Name',
            'username' => 'persona-test',
            'display_name' => 'persona-test',
            'email' => 'persona@example.test',
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
        Profile::create(['user_id' => $user->id, 'profile_type' => 'individual', 'lifestyle_identity' => 'single_man']);
        return $user;
    }
}
