<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OutboundUrl;
use Tests\TestCase;

final class OutboundUrlTest extends TestCase
{
    public function test_non_http_schemes_are_refused(): void
    {
        $this->assertFalse(OutboundUrl::safe('ftp://8.8.8.8/x'));
        $this->assertFalse(OutboundUrl::safe('file:///etc/passwd'));
        $this->assertFalse(OutboundUrl::safe('gopher://8.8.8.8'));
        $this->assertFalse(OutboundUrl::safe('not a url'));
    }

    public function test_link_local_and_metadata_addresses_are_always_refused(): void
    {
        config(['security.block_private_hosts' => false]);

        $this->assertFalse(OutboundUrl::safe('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(OutboundUrl::safe('http://169.254.0.1'));
        $this->assertFalse(OutboundUrl::safe('http://[fe80::1]'));
    }

    public function test_public_addresses_are_allowed(): void
    {
        $this->assertTrue(OutboundUrl::safe('https://8.8.8.8'));
        $this->assertTrue(OutboundUrl::safe('http://93.184.216.34/path'));
    }

    public function test_private_and_loopback_allowed_by_default_but_blockable(): void
    {
        config(['security.block_private_hosts' => false]);
        $this->assertTrue(OutboundUrl::safe('http://127.0.0.1:8000'));
        $this->assertTrue(OutboundUrl::safe('http://10.0.0.5'));
        $this->assertTrue(OutboundUrl::safe('http://192.168.1.20:9000'));

        config(['security.block_private_hosts' => true]);
        $this->assertFalse(OutboundUrl::safe('http://127.0.0.1:8000'));
        $this->assertFalse(OutboundUrl::safe('http://10.0.0.5'));
        $this->assertFalse(OutboundUrl::safe('http://192.168.1.20:9000'));
        // A public host stays reachable even when private ranges are blocked.
        $this->assertTrue(OutboundUrl::safe('https://8.8.8.8'));
    }
}
