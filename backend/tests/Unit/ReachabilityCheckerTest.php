<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Servers\ReachabilityChecker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReachabilityCheckerTest extends TestCase
{
    #[Test]
    public function it_refuses_a_host_the_outbound_guard_rejects(): void
    {
        // Link-local is refused everywhere in this app; a monitored host is no
        // exception, and the refusal is recorded rather than thrown.
        $results = (new ReachabilityChecker)->check('169.254.169.254', [22, 443]);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['ok']);
        $this->assertSame('unsafe_host', $results[0]['error']);
    }

    #[Test]
    public function it_reports_a_closed_port_as_refused_not_as_a_timeout(): void
    {
        // 127.0.0.1 on a port nothing binds: the stack answers immediately with
        // RST. Distinguishing that from silence is the point — refused means the
        // host is up and the service is not.
        $results = (new ReachabilityChecker)->check('127.0.0.1', [1]);

        $tcp = array_values(array_filter($results, static fn (array $r): bool => $r['kind'] === 'tcp'));
        $this->assertCount(1, $tcp);
        $this->assertFalse($tcp[0]['ok']);
        $this->assertSame(1, $tcp[0]['port']);
        $this->assertContains($tcp[0]['error'], ['refused', 'failed'], 'a closed local port must not read as a timeout');
    }

    #[Test]
    public function it_measures_an_open_port(): void
    {
        // Bind a real listener rather than assuming some service is up: the test
        // must prove the success path, not depend on the machine it runs on.
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped('cannot bind a local listener here');
        }
        $name = stream_socket_get_name($server, false);
        $port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);

        $results = (new ReachabilityChecker)->check('127.0.0.1', [$port]);
        fclose($server);

        $tcp = array_values(array_filter($results, static fn (array $r): bool => $r['kind'] === 'tcp'));
        $this->assertTrue($tcp[0]['ok']);
        $this->assertNotNull($tcp[0]['latency_ms']);
    }

    #[Test]
    public function it_checks_each_port_once(): void
    {
        $results = (new ReachabilityChecker)->check('127.0.0.1', [1, 1, 2]);

        $ports = array_map(
            static fn (array $r): ?int => $r['port'],
            array_values(array_filter($results, static fn (array $r): bool => $r['kind'] === 'tcp'))
        );
        $this->assertSame([1, 2], $ports);
    }
}
