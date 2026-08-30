<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use Aws\Exception\AwsException;
use Aws\S3\S3ClientInterface;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;

final readonly class S3AtomicDocumentObjectStore implements AtomicDocumentObjectStore
{
    public function __construct(
        private S3ClientInterface $client,
        private string $bucket,
        private string $prefix = '',
    ) {
        if ($bucket === '') {
            throw new LogicException('The S3 document bucket must be configured.');
        }
    }

    public function create(string $path, string $bytes, DocumentStorageWrite $write): void
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
                'Body' => $bytes,
                'ContentType' => 'application/pdf',
                'ContentMD5' => base64_encode(md5($bytes, true)),
                'IfNoneMatch' => '*',
                'Metadata' => $this->metadata($write),
            ]);
        } catch (AwsException $exception) {
            if (in_array($exception->getStatusCode(), [409, 412], true)) {
                throw new LogicException('The document capability is already in use.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function deleteIfOwned(string $path, DocumentStorageWrite $write): bool
    {
        $key = $this->key($path);

        try {
            $head = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404) {
                return false;
            }

            throw $exception;
        }

        $metadata = $head->get('Metadata');
        $etag = $head->get('ETag');
        if (! is_array($metadata)
            || ! is_string($etag)
            || ($metadata['ledgerline-proof'] ?? null) !== $write->cleanupProof
            || ($metadata['ledgerline-sha256'] ?? null) !== $write->sha256
            || ($metadata['ledgerline-generation'] ?? null) !== $write->generation()) {
            return false;
        }

        $lastModified = $head->get('LastModified');
        $contentLength = $head->get('ContentLength');
        if (! $lastModified instanceof DateTimeInterface || ! is_int($contentLength)) {
            throw new LogicException('The S3 object does not expose safe conditional-delete metadata.');
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'IfMatch' => $etag,
                'IfMatchLastModifiedTime' => $lastModified,
                'IfMatchSize' => $contentLength,
            ]);
        } catch (AwsException $exception) {
            if (in_array($exception->getStatusCode(), [404, 409, 412], true)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    public function ownedBefore(DateTimeInterface $cutoff): iterable
    {
        $continuationToken = null;

        do {
            $arguments = [
                'Bucket' => $this->bucket,
                'Prefix' => $this->financePrefix(),
            ];
            if ($continuationToken !== null) {
                $arguments['ContinuationToken'] = $continuationToken;
            }

            $listing = $this->client->listObjectsV2($arguments);
            $objects = $listing->get('Contents');
            if (is_array($objects)) {
                foreach ($objects as $object) {
                    if (! is_array($object)
                        || ! is_string($object['Key'] ?? null)
                        || ! ($object['LastModified'] ?? null) instanceof DateTimeInterface
                        || $object['LastModified'] >= $cutoff) {
                        continue;
                    }

                    $path = $this->pathFromKey($object['Key']);
                    if ($path === null) {
                        continue;
                    }

                    try {
                        $head = $this->client->headObject([
                            'Bucket' => $this->bucket,
                            'Key' => $object['Key'],
                        ]);
                    } catch (AwsException $exception) {
                        if ($exception->getStatusCode() === 404) {
                            continue;
                        }

                        throw $exception;
                    }

                    $metadata = $head->get('Metadata');
                    if (! is_array($metadata)
                        || ! is_string($metadata['ledgerline-proof'] ?? null)
                        || ! is_string($metadata['ledgerline-sha256'] ?? null)
                        || ! is_string($metadata['ledgerline-generation'] ?? null)) {
                        continue;
                    }

                    try {
                        $write = new DocumentStorageWrite(
                            ownershipToken: pathinfo($path, PATHINFO_FILENAME),
                            cleanupProof: $metadata['ledgerline-proof'],
                            sha256: $metadata['ledgerline-sha256'],
                        );
                    } catch (InvalidArgumentException) {
                        continue;
                    }

                    if (! hash_equals($write->generation(), $metadata['ledgerline-generation'])) {
                        continue;
                    }

                    yield ['path' => $path, 'write' => $write];
                }
            }

            $nextToken = $listing->get('NextContinuationToken');
            $continuationToken = is_string($nextToken) && $nextToken !== '' ? $nextToken : null;
        } while ($listing->get('IsTruncated') === true && $continuationToken !== null);
    }

    /** @return array{ledgerline-proof: string, ledgerline-sha256: string, ledgerline-generation: string} */
    private function metadata(DocumentStorageWrite $write): array
    {
        return [
            'ledgerline-proof' => $write->cleanupProof,
            'ledgerline-sha256' => $write->sha256,
            'ledgerline-generation' => $write->generation(),
        ];
    }

    private function key(string $path): string
    {
        if (preg_match('#\A[a-zA-Z0-9][a-zA-Z0-9._/-]*\.pdf\z#D', $path) !== 1
            || str_contains($path, '..')) {
            throw new LogicException('The document object path is unsafe.');
        }

        $prefix = trim($this->prefix, '/');

        return $prefix === '' ? $path : $prefix.'/'.$path;
    }

    private function financePrefix(): string
    {
        $prefix = trim($this->prefix, '/');

        return ($prefix === '' ? '' : $prefix.'/').'finance/revisions/';
    }

    private function pathFromKey(string $key): ?string
    {
        $prefix = trim($this->prefix, '/');
        $configuredPrefix = $prefix === '' ? '' : $prefix.'/';
        if (! str_starts_with($key, $configuredPrefix)) {
            return null;
        }

        $path = substr($key, strlen($configuredPrefix));
        if (preg_match('#\Afinance/revisions/([a-f0-9]{2})/([a-f0-9]{64})\.pdf\z#D', $path, $matches) !== 1
            || $matches[1] !== substr($matches[2], 0, 2)) {
            return null;
        }

        return $path;
    }
}
