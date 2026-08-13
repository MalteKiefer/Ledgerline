<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OutboundUrl;
use RuntimeException;
use Tests\TestCase;

final class OutboundUrlTest extends TestCase
{
    public function test_resolved_socket_target_pins_and_refuses_metadata_and_private(): void
    {
        config(['security.block_private_hosts' => false]);

        // A public / already-verified IP is returned verbatim (the pinned target).
        $target = OutboundUrl::resolvedSocketTarget('8.8.8.8');
        $this->assertSame('8.8.8.8', $target['ip']);
        $this->assertSame('8.8.8.8', $target['host']);
        $this->assertFalse($target['ipv6']);

        // link-local / cloud-metadata is ALWAYS refused (fail closed → throw).
        foreach (['169.254.169.254', '169.254.0.1', 'fe80::1'] as $bad) {
            try {
                OutboundUrl::resolvedSocketTarget($bad);
                $this->fail("expected refusal for {$bad}");
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }

        // Private is allowed by default, but refused (throws) in the hardened posture.
        $this->assertSame('10.0.0.5', OutboundUrl::resolvedSocketTarget('10.0.0.5')['ip']);
        config(['security.block_private_hosts' => true]);
        $this->expectException(RuntimeException::class);
        OutboundUrl::resolvedSocketTarget('10.0.0.5');
    }

    public function test_resolved_socket_target_refuses_empty_and_unresolvable(): void
    {
        try {
            OutboundUrl::resolvedSocketTarget('   ');
            $this->fail('expected refusal for empty host');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        // Unresolvable → fail closed (a socket cannot be re-pinned later).
        $this->expectException(RuntimeException::class);
        OutboundUrl::resolvedSocketTarget('nx-'.bin2hex(random_bytes(6)).'.invalid');
    }

    public function test_mail_port_allowlist(): void
    {
        foreach ([25, 143, 465, 587, 993] as $ok) {
            $this->assertTrue(OutboundUrl::mailPortAllowed($ok), "port {$ok} should be allowed");
        }
        foreach ([0, 80, 443, 22, 6379, 8080, 3306] as $bad) {
            $this->assertFalse(OutboundUrl::mailPortAllowed($bad), "port {$bad} should be refused");
        }
    }

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
