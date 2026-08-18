<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SubdirectoryFrontControllerTest extends TestCase
{
    public function test_canonical_script_identity_preserves_subdirectory_mount_and_root_route(): void
    {
        $request = Request::create(
            'https://demo.privoralabs.com/private-gather/',
            'GET',
            [],
            [],
            [],
            [
                'SCRIPT_NAME' => '/private-gather/index.php',
                'PHP_SELF' => '/private-gather/index.php',
                'SCRIPT_FILENAME' => '/var/www/html/private-gather/index.php',
            ],
        );

        $this->assertSame('/private-gather', $request->getBasePath());
        $this->assertSame('/', $request->getPathInfo());
    }
}
