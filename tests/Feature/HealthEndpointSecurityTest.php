<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointSecurityTest extends TestCase
{
    public function test_public_health_probe_exposes_only_aggregate_status(): void
    {
        $response = $this->getJson('http://platform.test/health');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->assertArrayNotHasKey('version', $response->json());
        $this->assertArrayNotHasKey('database', $response->json());
        $this->assertArrayNotHasKey('time', $response->json());
    }
}
