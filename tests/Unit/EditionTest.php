<?php

namespace Tests\Unit;

use App\Support\Edition;
use Tests\TestCase;

class EditionTest extends TestCase
{
    public function test_hosted_is_the_safe_default(): void
    {
        config(['edition.name' => 'hosted']);

        $this->assertTrue(Edition::isHosted());
        $this->assertFalse(Edition::isSelfHosted());
        $this->assertTrue(Edition::registrationEnabled());
    }

    public function test_self_hosted_policy_values_are_normalized(): void
    {
        config([
            'edition.name' => 'self_hosted',
            'edition.self_hosted.tenant_id' => '42',
            'edition.self_hosted.visibility' => 'private',
            'edition.self_hosted.registration' => 'approval',
        ]);

        $this->assertTrue(Edition::isSelfHosted());
        $this->assertSame(42, Edition::selfHostedTenantId());
        $this->assertSame('private', Edition::selfHostedVisibility());
        $this->assertSame('approval', Edition::selfHostedRegistration());
        $this->assertTrue(Edition::registrationEnabled());
    }

    public function test_disabled_self_hosted_registration_is_enforced(): void
    {
        config([
            'edition.name' => 'self_hosted',
            'edition.self_hosted.registration' => 'disabled',
        ]);

        $this->assertFalse(Edition::registrationEnabled());
    }
}
