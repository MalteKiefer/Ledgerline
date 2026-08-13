<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the file vault to a fully zero-knowledge model.
 *
 * The server no longer stores one row per file or folder — even existence,
 * counts, tree shape, sizes and timestamps were metadata leaks. Instead the
 * whole directory structure (folders, names, mime types, sizes, per-file keys,
 * tags) lives in a single manifest blob encrypted with the vault key, and file
 * contents are opaque, padded blobs on object storage keyed by random UUIDs.
 *
 * The old files/folders/tags tables are dropped and the vault is reset (the
 * user chose a fresh start): setup runs again and a new manifest begins empty.
 * Destructive and irreversible by design.
 */
return new class extends Migration
{
    private array $tables = ['file_tag', 'folder_tag', 'files', 'folders', 'tags'];

    public function up(): void
    {
        $pg = Schema::getConnection()->getDriverName() === 'pgsql';

        foreach ($this->tables as $table) {
            if ($pg) {
                Schema::getConnection()->statement("drop table if exists \"{$table}\" cascade");
            } else {
                Schema::dropIfExists($table);
            }
        }

        Schema::table('vault', function (Blueprint $table): void {
            // The encrypted manifest plus an optimistic-locking version so two
            // tabs cannot silently overwrite each other's structure changes.
            $table->longText('manifest_cipher')->nullable();
            $table->string('manifest_nonce')->nullable();
            $table->unsignedBigInteger('manifest_version')->default(0);
        });

        // Fresh start: the vault (passphrase wrap + recovery) is recreated by
        // the user on next sign-in.
        DB::table('vault')->delete();
    }

    public function down(): void
    {
        // Irreversible: the previous per-row file model is gone for good.
    }
};
