<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use InvalidArgumentException;

final readonly class StoredDocument
{
    public function __construct(public string $path, public string $sha256)
    {
        if (! self::isSafePdfPath($path)) {
            throw new InvalidArgumentException('A stored PDF path must be a safe relative server path.');
        }

        if (preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1) {
            throw new InvalidArgumentException('A stored document hash must be a lowercase SHA-256 digest.');
        }
    }

    private static function isSafePdfPath(string $path): bool
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._\/-]*\.pdf\z/D', $path) !== 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
