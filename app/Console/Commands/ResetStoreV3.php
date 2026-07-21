<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Store v3 clean-slate reset (spec §10.3/§13-A3).
 *
 * ACCEPTED DATA LOSS — the owner keeps external backups. There is no migration
 * from v1/v2; this command drops all sealed stores + blob ledgers + disk blobs so
 * clients re-initialise fresh into the v3 format on next unlock. Identity keys are
 * nulled so each user regenerates a fresh hybrid (X25519 + ML-KEM-768) identity.
 *
 * Ops-gated: refuses to run in production without --force, and always confirms
 * interactively. Destructive and irreversible.
 */
class ResetStoreV3 extends Command
{
    protected $signature = 'store:reset-v3 {--force : Skip the production guard and the confirmation prompt}';

    protected $description = 'Clean-slate wipe of all sealed stores, blob ledgers and disk blobs for the Store v3 rebuild (irreversible, accepted data loss).';

    /** Disk prefixes that hold client-ciphertext blobs (all module blob trees). */
    private const BLOB_PREFIXES = ['gallery', 'files', 'shared-folders', 'contacts'];

    /** Ledger + sealed-store tables to truncate. */
    private const TABLES = [
        'gallery_blobs',
        'file_blobs',
        'shared_folder_blobs',
        'contact_blobs',
        'gallery_store',
        'files_store',
        'module_stores',
        'shared_vault_stores',
        'shared_vault_members',
        'shared_vaults',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (app()->environment('production') && ! $force) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $this->warn('This IRREVERSIBLY deletes ALL gallery/files/shared/contact blobs,');
        $this->warn('every sealed store row, and all shared vaults. Identity keys are reset.');
        if (! $force && ! $this->confirm('Proceed with the clean-slate Store v3 reset?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // 1. Disk blobs — delete each ciphertext blob tree wholesale.
        $disk = BlobStore::disk();
        foreach (self::BLOB_PREFIXES as $prefix) {
            $disk->deleteDirectory($prefix);
            $this->line("  deleted disk blobs: {$prefix}/");
        }

        // 2. Ledger + store rows.
        foreach (self::TABLES as $table) {
            DB::table($table)->delete();
            $this->line("  cleared table: {$table}");
        }

        // 3. Identity keys — force every user to regenerate a fresh v3 hybrid identity
        //    (x25519 + ML-KEM-768) on next unlock; old wrapped material is now meaningless.
        DB::table('users')->update([
            'x25519_public_key' => null,
            'wrapped_x25519_secret_key' => null,
            'public_key_fingerprint' => null,
            'mlkem_public_key' => null,
            'wrapped_mlkem_secret_key' => null,
        ]);
        $this->line('  reset all user identity keypairs');

        $this->info('Store v3 clean-slate reset complete. Clients will re-initialise on next unlock.');

        return self::SUCCESS;
    }
}
