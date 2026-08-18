<?php

namespace Tests\Unit;

use App\Support\TenantUrl;
use Tests\TestCase;

class TenantUrlTest extends TestCase
{
    public function test_subdirectory_install_keeps_mount_in_public_tenant_url(): void
    {
        config([
            'platform.root_domain' => 'demo.privoralabs.com',
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '/private-gather',
            'platform.wildcard_target' => 'demo.privoralabs.com',
        ]);

        $this->assertSame(
            'https://midnight.demo.privoralabs.com/private-gather/',
            TenantUrl::to('midnight.demo.privoralabs.com'),
        );
        $this->assertSame(
            'https://midnight.demo.privoralabs.com/private-gather/manage',
            TenantUrl::to('midnight.demo.privoralabs.com', '/manage'),
        );
        $this->assertSame('*.demo.privoralabs.com', TenantUrl::wildcardPattern());
        $this->assertSame('demo.privoralabs.com', TenantUrl::wildcardTarget());
    }

    public function test_root_install_generates_clean_public_tenant_url(): void
    {
        config([
            'platform.root_domain' => 'privategather.com',
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
        ]);

        $this->assertSame(
            'https://club.privategather.com/',
            TenantUrl::to('club.privategather.com'),
        );
    }

    public function test_invalid_tenant_host_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantUrl::to('bad host');
    }
}
