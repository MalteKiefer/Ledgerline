<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop dead admin-surface columns left over from the finance-only pivot, and
 * migrate the per-user company SMTP host/username/from-address to encrypted-at-rest.
 *
 *  - users.files_quota_mb / users.gallery_quota_mb: the files/gallery storage
 *    modules were removed; nothing enforces an aggregate storage quota anymore
 *    (their only readers, User::effectiveFilesQuotaMb()/effectiveGalleryQuotaMb(),
 *    are deleted in the same release). Dead, misleading admin controls.
 *  - users.oidc_sub: re-added for an optional Pocket-ID/OIDC sign-in that no
 *    controller implements (the guest route group is empty); no reader or writer.
 *
 * Also re-encrypts any existing PLAINTEXT company_smtp_host / _username /
 * _from_address so the new `encrypted` casts on UserSetting decrypt cleanly.
 * Idempotent: a value that already decrypts is left untouched.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $smtpSecrets = ['company_smtp_host', 'company_smtp_username', 'company_smtp_from_address'];

    public function up(): void
    {
        $this->encryptExistingSmtpSecrets();

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'oidc_sub')) {
                // Drop the UNIQUE index before the column (portable to SQLite).
                try {
                    $table->dropUnique(['oidc_sub']);
                } catch (Throwable) {
                    // Index name may differ / already gone — the column drop below still runs.
                }
                $table->dropColumn('oidc_sub');
            }
            if (Schema::hasColumn('users', 'files_quota_mb')) {
                $table->dropColumn('files_quota_mb');
            }
            if (Schema::hasColumn('users', 'gallery_quota_mb')) {
                $table->dropColumn('gallery_quota_mb');
            }
        });

        // Group storage-quota columns are dead too (no aggregate storage quota is
        // enforced in the finance-only app; only the device cap remains a group limit).
        Schema::table('groups', function (Blueprint $table): void {
            foreach (['files_quota_mb', 'gallery_quota_mb'] as $col) {
                if (Schema::hasColumn('groups', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'oidc_sub')) {
                $table->string('oidc_sub')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('users', 'files_quota_mb')) {
                $table->unsignedBigInteger('files_quota_mb')->nullable();
            }
            if (! Schema::hasColumn('users', 'gallery_quota_mb')) {
                $table->unsignedBigInteger('gallery_quota_mb')->nullable();
            }
        });
        Schema::table('groups', function (Blueprint $table): void {
            foreach (['files_quota_mb', 'gallery_quota_mb'] as $col) {
                if (! Schema::hasColumn('groups', $col)) {
                    $table->unsignedBigInteger($col)->nullable();
                }
            }
        });
        // The SMTP-secret re-encryption is a one-way, at-rest hardening and is not
        // reversed here (there is no safe way to restore plaintext, and none is wanted).
    }

    /**
     * Re-encrypt any company SMTP endpoint/identity value still stored in plaintext.
     * Reads raw (bypassing the model casts) and only rewrites values that do NOT
     * already decrypt, so re-running the migration is a no-op.
     */
    private function encryptExistingSmtpSecrets(): void
    {
        if (! Schema::hasTable('user_settings')) {
            return;
        }
        $columns = array_values(array_filter(
            $this->smtpSecrets,
            static fn (string $c): bool => Schema::hasColumn('user_settings', $c),
        ));
        if ($columns === []) {
            return;
        }

        foreach (DB::table('user_settings')->get(array_merge(['user_id'], $columns)) as $row) {
            $updates = [];
            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }
                try {
                    Crypt::decryptString($value); // already ciphertext → leave it
                } catch (Throwable) {
                    $updates[$column] = Crypt::encryptString($value); // plaintext → encrypt
                }
            }
            if ($updates !== []) {
                DB::table('user_settings')->where('user_id', $row->user_id)->update($updates);
            }
        }
    }
};
