<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlMailSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlMailSanitizerTest extends TestCase
{
    public function test_keeps_safe_formatting_and_links(): void
    {
        $out = HtmlMailSanitizer::clean('<p>Hello <strong>bold</strong> <a href="https://x.test">link</a></p>');
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringContainsString('href="https://x.test"', $out);
    }

    public function test_strips_scripts_and_event_handlers(): void
    {
        $out = HtmlMailSanitizer::clean('<p onclick="steal()">hi</p><script>evil()</script>');
        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('hi', $out);
    }

    public function test_neutralises_javascript_urls(): void
    {
        $out = HtmlMailSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_empty_in_empty_out(): void
    {
        $this->assertSame('', HtmlMailSanitizer::clean(''));
        $this->assertSame('', HtmlMailSanitizer::clean('   '));
    }
}
