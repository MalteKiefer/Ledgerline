<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

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

    /**
     * Build from a stored row. Three callers now reach a host from a saved
     * server — the collector, the log reader and the terminal — and they must
     * not drift on which fields matter or how the credential is unpacked.
     */
    public static function fromServer(Server $server): self
    {
        $credentials = $server->credentials ?? [];

        return new self(
            host: $server->host,
            port: $server->port,
            username: $server->username,
            privateKey: is_string($credentials['private_key'] ?? null) ? $credentials['private_key'] : '',
            passphrase: is_string($credentials['passphrase'] ?? null) ? $credentials['passphrase'] : '',
            fingerprint: (string) $server->host_fingerprint,
            hostKey: (string) $server->host_key,
        );
    }
}
