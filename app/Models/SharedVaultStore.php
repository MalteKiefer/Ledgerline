<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The opaque sealed manifest for a shared password-Tresor. One row per vault;
 * vault_id is both the FK and the primary key. version drives optimistic
 * concurrency so two concurrent saves cannot silently clobber each other.
 * The server never reads inside sealed_manifest.
 */
#[Fillable(['vault_id', 'sealed_manifest', 'version'])]
class SharedVaultStore extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'vault_id';

    protected $keyType = 'string';

    /**
     * Attempt to write a new sealed_manifest if the current version matches
     * expected_version.
     *
     * Wraps the lockForUpdate + version-check + update pattern in a single
     * DB transaction. Returns a result array:
     *   - on success:  ['conflict' => false, 'version' => N]
     *   - on conflict: ['conflict' => true,  'version' => N, 'sealed_manifest' => '...']
     *
     * @return array{conflict: false, version: int}|array{conflict: true, version: int, sealed_manifest: string|null}
     */
    public static function tryWrite(string|int $vaultId, string $sealedManifest, int $expectedVersion): array
    {
        return DB::transaction(function () use ($vaultId, $sealedManifest, $expectedVersion): array {
            /** @var static|null $row */
            $row = static::where('vault_id', $vaultId)->lockForUpdate()->first();

            $current = (int) ($row?->version ?? 0);

            if ($current !== $expectedVersion) {
                return [
                    'conflict' => true,
                    'version' => $current,
                    'sealed_manifest' => $row?->sealed_manifest,
                ];
            }

            $nextVersion = $current + 1;

            static::where('vault_id', $vaultId)->update([
                'sealed_manifest' => $sealedManifest,
                'version' => $nextVersion,
            ]);

            return ['conflict' => false, 'version' => $nextVersion];
        });
    }
}
