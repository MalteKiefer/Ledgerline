<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

/**
 * Re-encrypt every `encrypted`-cast column from AES-256-CBC to AES-256-GCM.
 *
 * GCM is AEAD (authenticated encryption) — a tampered ciphertext fails to
 * decrypt instead of silently returning garbage — so it is the stronger cipher
 * for secrets at rest. Laravel decrypts with whatever `app.cipher` is configured;
 * switching the config alone would make every existing (CBC) value undecryptable.
 * This migration converts the stored values, using EXPLICIT ciphers (not the app
 * default) so it is correct regardless of the order relative to config:cache.
 *
 * Data-loss safety:
 *   - Each value is round-trip verified (decrypt-back == plaintext) BEFORE it is
 *     written; a mismatch throws and rolls the whole migration back.
 *   - Rows whose value does not decrypt under the source cipher are skipped, so
 *     the migration is idempotent and never overwrites a value it cannot cleanly
 *     convert (already-migrated rows, or unrelated data).
 *   - The entire run is wrapped in one transaction: partial conversion is
 *     impossible — it is all-or-nothing.
 */
return new class extends Migration
{
    /** [table, [encrypted columns]] — every `encrypted` cast in the codebase. */
    private const TARGETS = [
        ['backup_destinations', ['config']],
        ['backup_jobs', ['passphrase']],
        ['user_settings', ['paperless_url', 'paperless_token']],
        ['app_settings', [
            'smtp_host', 'smtp_username', 'smtp_password', 'smtp_from_address', 'smtp_from_name',
            'ntfy_url', 'ntfy_topic', 'ntfy_token',
            'webhook_url', 'webhook_secret',
        ]],
    ];

    public function up(): void
    {
        $this->recrypt('AES-256-CBC', 'AES-256-GCM');
    }

    public function down(): void
    {
        $this->recrypt('AES-256-GCM', 'AES-256-CBC');
    }

    private function recrypt(string $from, string $to): void
    {
        $appKey = (string) config('app.key');
        $key = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7))
            : $appKey;

        $src = new Encrypter($key, $from);
        $dst = new Encrypter($key, $to);

        DB::transaction(function () use ($src, $dst): void {
            foreach (self::TARGETS as [$table, $columns]) {
                DB::table($table)->orderBy('id')->each(function (object $row) use ($src, $dst, $table, $columns): void {
                    foreach ($columns as $column) {
                        $raw = $row->{$column} ?? null;
                        if (! is_string($raw) || $raw === '') {
                            continue;
                        }

                        // The `encrypted` cast stores strings with serialize=false;
                        // operate on that same raw layer so we stay oblivious to
                        // whether the underlying cast was string/array/json.
                        try {
                            $plain = $src->decrypt($raw, false);
                        } catch (DecryptException) {
                            continue; // not encrypted under $from → leave untouched
                        }

                        $reencrypted = $dst->encrypt($plain, false);

                        if ($dst->decrypt($reencrypted, false) !== $plain) {
                            throw new RuntimeException("Re-encrypt verify failed for {$table}.{$column} id={$row->id}");
                        }

                        DB::table($table)->where('id', $row->id)->update([$column => $reencrypted]);
                    }
                });
            }
        });
    }
};
