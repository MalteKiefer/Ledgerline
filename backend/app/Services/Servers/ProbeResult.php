<?php

declare(strict_types=1);

namespace App\Services\Servers;

/**
 * The outcome of one SSH probe: either a parsed snapshot or the reason it failed,
 * plus the host key fingerprint we saw (needed for trust-on-first-use when the
 * server has no pin stored yet).
 */
final readonly class ProbeResult
{
    /** @param  array<string, mixed>  $facts */
    public function __construct(
        public bool $ok,
        public array $facts = [],
        public ?string $error = null,
        public ?string $fingerprint = null,
        public int $durationMs = 0,
    ) {}
}
