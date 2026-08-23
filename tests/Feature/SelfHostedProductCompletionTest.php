<?php

namespace Tests\Feature;

use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SelfHostedProductCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Edition::isSelfHosted()) $this->markTestSkipped('Self-Hosted completion contract runs only under the self_hosted edition preset.');
    }

    public function test_shared_completion_schema_and_private_features_remain_available(): void
    {
        foreach (['favorites','saved_searches','user_connections','user_privacy_settings','reviews','membership_subscriptions','push_subscriptions','trust_cases'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' missing in Self-Hosted edition');
        }
        foreach (['onboarding.index','saved.index','notifications.index','privacy.index','connections.index','members.discover','tenant.crm.index','tenant.commerce.index','tenant.builder.index','tenant.growth.index'] as $name) {
            $this->assertTrue(Route::has($name), $name.' missing in Self-Hosted edition');
            $this->assertContains('web', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
    }

    public function test_hosted_network_discovery_routes_stay_disabled(): void
    {
        $this->assertFalse(Route::has('discover.index'));
        $this->assertFalse(Route::has('clubs.show'));
    }
}
