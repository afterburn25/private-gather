<?php

namespace Tests\Unit;

use App\Support\MountUrl;
use Tests\TestCase;

class MountUrlSecurityTest extends TestCase
{
    public function test_allows_expected_web_and_navigation_urls(): void
    {
        $this->assertSame('https://example.test/path?q=1', MountUrl::to('https://example.test/path?q=1'));
        $this->assertSame('http://example.test/path', MountUrl::to('http://example.test/path'));
        $this->assertSame('//cdn.example.test/image.png', MountUrl::to('//cdn.example.test/image.png'));
        $this->assertSame('mailto:member@example.test', MountUrl::to('mailto:member@example.test'));
        $this->assertSame('tel:+15551234567', MountUrl::to('tel:+15551234567'));
        $this->assertSame('#faq', MountUrl::to('#faq'));
        $this->assertSame('events/upcoming', MountUrl::to('events/upcoming'));
        $this->assertSame('/events', MountUrl::to('/events'));
    }

    public function test_rejects_script_data_file_and_unknown_schemes(): void
    {
        foreach ([
            'javascript:alert(1)',
            'JaVaScRiPt:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            'custom+scheme:payload',
        ] as $unsafe) {
            $this->assertSame('', MountUrl::to($unsafe), $unsafe.' should be rejected');
        }
    }

    public function test_rejects_control_character_scheme_obfuscation(): void
    {
        $this->assertSame('', MountUrl::to("java\tscript:alert(1)"));
        $this->assertSame('', MountUrl::to("java\nscript:alert(1)"));
        $this->assertSame('', MountUrl::to("https://example.test/\x7Fhidden"));
    }
}
