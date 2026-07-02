<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One encrypted directory/content manifest per zero-knowledge module (files,
 * notes, …). The server only ever swaps whole ciphertexts, guarded by an
 * optimistic version.
 */
#[Fillable(['name', 'cipher', 'nonce', 'version'])]
class VaultManifest extends Model
{
    /**
     * The manifest for a module, created empty on first access.
     */
    public static function named(string $name): self
    {
        return static::query()->firstOrCreate(['name' => $name]);
    }
}
