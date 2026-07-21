<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupDestination;
use App\Support\OutboundUrl;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\WebDAV\WebDAVAdapter;
use RuntimeException;
use Sabre\DAV\Client as WebDavClient;

/**
 * Builds a Flysystem filesystem for a backup destination. S3 and Backblaze B2
 * share the S3 adapter (B2 via its S3-compatible endpoint); SFTP and WebDAV use
 * their own adapters.
 */
class BackupDestinationFactory
{
    public function make(BackupDestination $destination): Filesystem
    {
        return $this->makeFromParts($destination->driver, $destination->config ?? []);
    }

    /**
     * Build a filesystem from a raw driver + config (used to test a destination
     * before it is saved).
     *
     * @param  array<string, mixed>  $c
     */
    public function makeFromParts(string $driver, array $c): Filesystem
    {
        return new Filesystem(match ($driver) {
            's3', 'b2' => $this->s3($c),
            'sftp' => $this->sftp($c),
            'webdav' => $this->webdav($c),
            default => throw new RuntimeException("Unknown backup driver: {$driver}"),
        });
    }

    /**
     * Verify a destination is reachable and writable by writing then deleting a
     * tiny probe object. Throws with the underlying reason on failure.
     *
     * @param  array<string, mixed>  $config
     */
    public function test(string $driver, array $config): void
    {
        $fs = $this->makeFromParts($driver, $config);
        // Create the target folder up front so testing a not-yet-existing path
        // succeeds (and leaves the folder ready for the first backup) instead of
        // failing on the probe write.
        $this->ensureRoot($fs, $driver, $config);
        // Plain (non-dot) filename so no host hides/rejects it; written + deleted.
        $probe = 'ledgerline-connection-test-'.bin2hex(random_bytes(6)).'.txt';
        $fs->write($probe, "ok\n");
        $fs->delete($probe);
    }

    /**
     * Best-effort create of the configured destination root directory. Directory
     * based drivers (SFTP/WebDAV) do not auto-create the root prefix when a file
     * is written at its top level — so a valid host whose target folder does not
     * exist yet would fail the connection test and the first backup. Object
     * stores (S3/B2) create keys on write and need nothing here.
     *
     * Throws (does not swallow) so a genuine failure — e.g. no permission to
     * create the folder — is surfaced to the user rather than hidden.
     *
     * @param  array<string, mixed>  $config
     */
    public function ensureRoot(Filesystem $fs, string $driver, array $config): void
    {
        if (! in_array($driver, ['sftp', 'webdav'], true)) {
            return;
        }
        // No path configured means the login/base directory, which already exists.
        if (trim((string) ($config['path'] ?? ''), '/') === '') {
            return;
        }
        // The empty path resolves to the root prefix; the adapter mkdir's the
        // whole chain recursively.
        $fs->createDirectory('');
    }

    /**
     * @param  array{region?: string, key?: string, secret?: string, endpoint?: string, use_path_style?: bool, bucket?: string, path?: string}  $c
     */
    private function s3(array $c): AwsS3V3Adapter
    {
        $args = [
            'version' => 'latest',
            'region' => $c['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $c['key'] ?? '',
                'secret' => $c['secret'] ?? '',
            ],
        ];
        // B2 (and other S3-compatible stores) need a custom endpoint + path-style.
        if (! empty($c['endpoint'])) {
            $this->assertHostAllowed((string) (parse_url((string) $c['endpoint'], PHP_URL_HOST) ?: ''));
            $args['endpoint'] = $c['endpoint'];
            $args['use_path_style_endpoint'] = (bool) ($c['use_path_style'] ?? true);
        }

        return new AwsS3V3Adapter(new S3Client($args), $c['bucket'] ?? '', trim((string) ($c['path'] ?? ''), '/'));
    }

    /**
     * @param  array{path?: string, host_fingerprint?: string, host?: string, username?: string, password?: string, private_key?: string, port?: int|string}  $c
     */
    private function sftp(array $c): SftpAdapter
    {
        // Root defaults to the login directory (empty), NOT '/': on many SFTP
        // hosts (e.g. Hetzner Storage Box) the absolute server root is not
        // writable, but the home dir is. A configured path is used as-is.
        $root = trim((string) ($c['path'] ?? ''));

        // Pin the server's host key when a fingerprint is configured, so a
        // MITM / DNS-spoof of the SFTP host cannot capture the credentials and
        // backup. Without a fingerprint the connection is trust-on-first-use.
        $fingerprint = trim((string) ($c['host_fingerprint'] ?? ''));

        $this->assertHostAllowed((string) ($c['host'] ?? ''));

        return new SftpAdapter(
            new SftpConnectionProvider(
                host: $c['host'] ?? '',
                username: $c['username'] ?? '',
                password: ($c['password'] ?? '') !== '' ? $c['password'] : null,
                privateKey: ($c['private_key'] ?? '') !== '' ? $c['private_key'] : null,
                port: (int) ($c['port'] ?? 22),
                hostFingerprint: $fingerprint !== '' ? $fingerprint : null,
            ),
            $root,
        );
    }

    /**
     * @param  array{base_uri?: string, username?: string, password?: string, path?: string}  $c
     */
    private function webdav(array $c): WebDAVAdapter
    {
        $this->assertHostAllowed((string) (parse_url((string) ($c['base_uri'] ?? ''), PHP_URL_HOST) ?: ''));

        $client = new WebDavClient([
            'baseUri' => $c['base_uri'] ?? '',
            'userName' => $c['username'] ?? '',
            'password' => $c['password'] ?? '',
        ]);

        return new WebDAVAdapter($client, trim((string) ($c['path'] ?? ''), '/'));
    }

    /**
     * Re-check the resolved destination host against the shared outbound allow
     * logic immediately before connecting, so a DNS-rebind between validation
     * and connect (or a config that bypassed validation) cannot reach a
     * link-local / cloud-metadata address. Fails closed.
     */
    private function assertHostAllowed(string $host): void
    {
        if ($host !== '' && ! OutboundUrl::hostAllowed($host)) {
            throw new RuntimeException('Refusing to connect to an unsafe backup host.');
        }
    }
}
