<?php

declare(strict_types=1);

namespace App\Services\Servers;

/**
 * Everything one probe needs to reach a host, in one value instead of six
 * positional arguments — the controller and the collector build it from
 * different sources (a request vs. a stored row) and must not drift apart.
 */
final readonly class ServerTarget
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        /** OpenSSH or PEM private key. */
        public string $privateKey = '',
        /** Passphrase for an encrypted key; the key is decrypted before use. */
        public string $passphrase = '',
        /** Pinned fingerprint (SHA256:…), or empty to learn it. */
        public string $fingerprint = '',
        /**
         * The pinned host key itself ("<type> <base64>"). Stored alongside the
         * fingerprint because OpenSSH enforces a pin from a known_hosts entry,
         * which needs the whole key — a fingerprint alone cannot be checked by
         * ssh, only by us after the fact.
         */
        public string $hostKey = '',
    ) {}
}
