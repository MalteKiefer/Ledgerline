<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use LogicException;
use RuntimeException;

final readonly class LocalAtomicDocumentObjectStore implements AtomicDocumentObjectStore
{
    public function __construct(
        private string $root,
        private string $lockRoot,
    ) {
        if ($root === '' || $lockRoot === '') {
            throw new LogicException('Local document object and lock roots must be configured.');
        }
    }

    public function create(string $path, string $bytes, DocumentStorageWrite $write): void
    {
        $this->withLock($write, function () use ($path, $bytes, $write): void {
            $absolutePath = $this->absolutePath($path);
            $metadataPath = $absolutePath.'.ledgerline-owner';
            $this->ensureDirectory(dirname($absolutePath));

            if (is_file($absolutePath) || is_file($metadataPath)) {
                throw new LogicException('The document capability is already in use.');
            }

            $temporaryPath = $absolutePath.'.tmp-'.$write->cleanupProof;
            try {
                $this->writeNewFile($temporaryPath, $bytes);
                if (! @link($temporaryPath, $absolutePath)) {
                    throw new LogicException('The document capability is already in use.');
                }

                try {
                    $metadata = json_encode([
                        'version' => 1,
                        'cleanup_proof' => $write->cleanupProof,
                        'sha256' => $write->sha256,
                        'generation' => $write->generation(),
                    ], JSON_THROW_ON_ERROR);
                    $this->writeNewFile($metadataPath, $metadata);
                } catch (\Throwable $exception) {
                    if (hash_file('sha256', $absolutePath) === $write->sha256) {
                        @unlink($absolutePath);
                    }

                    throw $exception;
                }
            } finally {
                @unlink($temporaryPath);
            }
        });
    }

    public function deleteIfOwned(string $path, DocumentStorageWrite $write): void
    {
        $this->withLock($write, function () use ($path, $write): void {
            $absolutePath = $this->absolutePath($path);
            $metadataPath = $absolutePath.'.ledgerline-owner';
            $metadataJson = @file_get_contents($metadataPath);
            if (! is_string($metadataJson)) {
                return;
            }

            $metadata = json_decode($metadataJson, true);
            if (! is_array($metadata)
                || ($metadata['cleanup_proof'] ?? null) !== $write->cleanupProof
                || ($metadata['sha256'] ?? null) !== $write->sha256
                || ($metadata['generation'] ?? null) !== $write->generation()) {
                return;
            }

            if (! is_file($absolutePath)) {
                @unlink($metadataPath);

                return;
            }

            if (hash_file('sha256', $absolutePath) !== $write->sha256) {
                return;
            }

            if (! @unlink($absolutePath) && is_file($absolutePath)) {
                throw new RuntimeException('The owned document object could not be deleted.');
            }
            if (! @unlink($metadataPath) && is_file($metadataPath)) {
                throw new RuntimeException('The document ownership metadata could not be deleted.');
            }
        });
    }

    /** @param callable(): void $operation */
    private function withLock(DocumentStorageWrite $write, callable $operation): void
    {
        $lockPath = $this->lockPath($write);
        $this->ensureDirectory(dirname($lockPath));
        $handle = @fopen($lockPath, 'c+b');
        if (! is_resource($handle)) {
            throw new RuntimeException('The document capability lock could not be opened.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('The document capability lock could not be acquired.');
            }

            $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('#\A[a-zA-Z0-9][a-zA-Z0-9._/-]*\.pdf\z#D', $path) !== 1
            || str_contains($path, '..')) {
            throw new LogicException('The document object path is unsafe.');
        }

        return rtrim($this->root, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function lockPath(DocumentStorageWrite $write): string
    {
        return rtrim($this->lockRoot, '\\/').DIRECTORY_SEPARATOR
            .substr($write->ownershipToken, 0, 2).DIRECTORY_SEPARATOR
            .$write->ownershipToken.'.lock';
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('The document storage directory could not be created.');
        }
    }

    private function writeNewFile(string $path, string $bytes): void
    {
        $handle = @fopen($path, 'x+b');
        if (! is_resource($handle)) {
            throw new LogicException('The document capability is already in use.');
        }

        try {
            $offset = 0;
            $length = strlen($bytes);
            while ($offset < $length) {
                $written = fwrite($handle, substr($bytes, $offset));
                if (! is_int($written) || $written < 1) {
                    throw new RuntimeException('The document object could not be written completely.');
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new RuntimeException('The document object could not be flushed.');
            }
            if (function_exists('fsync') && ! fsync($handle)) {
                throw new RuntimeException('The document object could not be synchronized.');
            }
        } finally {
            fclose($handle);
        }
    }
}
