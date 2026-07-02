<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupDestination;
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
final class BackupDestinationFactory
{
    public function make(BackupDestination $destination): Filesystem
    {
        $c = $destination->config ?? [];

        return new Filesystem(match ($destination->driver) {
            's3', 'b2' => $this->s3($c),
            'sftp' => $this->sftp($c),
            'webdav' => $this->webdav($c),
            default => throw new RuntimeException("Unknown backup driver: {$destination->driver}"),
        });
    }

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
            $args['endpoint'] = $c['endpoint'];
            $args['use_path_style_endpoint'] = (bool) ($c['use_path_style'] ?? true);
        }

        return new AwsS3V3Adapter(new S3Client($args), $c['bucket'] ?? '', trim((string) ($c['path'] ?? ''), '/'));
    }

    private function sftp(array $c): SftpAdapter
    {
        return new SftpAdapter(
            new SftpConnectionProvider(
                host: $c['host'] ?? '',
                username: $c['username'] ?? '',
                password: $c['password'] ?? null,
                privateKey: $c['private_key'] ?? null,
                port: (int) ($c['port'] ?? 22),
            ),
            $c['path'] ?? '/',
        );
    }

    private function webdav(array $c): WebDAVAdapter
    {
        $client = new WebDavClient([
            'baseUri' => $c['base_uri'] ?? '',
            'userName' => $c['username'] ?? '',
            'password' => $c['password'] ?? '',
        ]);

        return new WebDAVAdapter($client, trim((string) ($c['path'] ?? ''), '/'));
    }
}
