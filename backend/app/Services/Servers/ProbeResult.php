<?php

declare(strict_types=1);

namespace App\Services\Servers;

/**
 * The outcome of one SSH probe: either a parsed snapshot or the reason it failed,
 * plus the host key we saw. Both halves of the key matter — the fingerprint is
 * what a human confirms, the key itself is what OpenSSH needs in known_hosts to
 * enforce the pin on every later connection.
 */
final readonly class ProbeResult
{
    /** @param  array<string, mixed>  $facts */
    public function __construct(
        public bool $ok,
        public array $facts = [],
        public ?string $error = null,
        public ?string $fingerprint = null,
        public ?string $hostKey = null,
        public int $durationMs = 0,
    ) {}
}
