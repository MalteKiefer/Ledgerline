<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\OutboundUrl;
use RuntimeException;

/**
 * A real socket-backed {@see ImapStream}: opens a verified TLS/TCP connection to
 * an origin IMAP server and exposes just enough line I/O for the raw-IMAP
 * write-to-origin clients. There is no ext-imap in the runtime image, so the
 * clients speak the wire protocol directly over this stream.
 */
final class ImapConnection implements ImapStream
{
    /** @param  resource  $sock */
    private function __construct(private $sock) {}

    /**
     * Open a connection. `ssl`/`tls` use implicit TLS on connect; `starttls`/
     * `none` connect over plain TCP (the client upgrades with enableCrypto()).
     * The peer certificate is always verified.
     */
    public static function open(string $transport, string $host, int $port, int $connectTimeout, int $ioTimeout): self
    {
        if (! OutboundUrl::mailPortAllowed($port)) {
            throw new RuntimeException('IMAP: port not allowed');
        }

        // Pin the resolved IP (resolve once, connect to exactly that address) so a
        // short-TTL record can't answer a safe IP to the guard and a private/
        // metadata IP to the real connect. peer_name keeps TLS verification (and
        // STARTTLS's later enableCrypto) bound to the hostname, not the literal IP.
        $target = OutboundUrl::resolvedSocketTarget($host);
        $addr = $target['ipv6'] ? '['.$target['ip'].']' : $target['ip'];

        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $target['host'],
        ]]);
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            "{$transport}://{$addr}:{$port}",
            $errno,
            $errstr,
            $connectTimeout,
            STREAM_CLIENT_CONNECT,
            $ctx,
        );
        if ($sock === false) {
            throw new RuntimeException('IMAP: cannot connect');
        }
        stream_set_timeout($sock, $ioTimeout);

        return new self($sock);
    }

    public function readLine(): string
    {
        $line = @fgets($this->sock);
        $meta = stream_get_meta_data($this->sock);
        if ($line === false || ! empty($meta['timed_out'])) {
            throw new RuntimeException('IMAP: read timeout');
        }

        return rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
        if (@fwrite($this->sock, $data) === false) {
            throw new RuntimeException('IMAP: write failed');
        }
    }

    public function enableCrypto(): bool
    {
        return stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
    }

    public function close(): void
    {
        @fclose($this->sock);
    }
}
