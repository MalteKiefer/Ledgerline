<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encryption fully removed for USER CONTENT (owner decision "alle encryption weg").
 * The pivot's `encrypted`/`encrypted:array` casts on user data are dropped in the
 * models; this migration rewrites the already-encrypted at-rest values back to
 * plaintext IN PLACE so the un-cast models read them correctly.
 *
 * Operational SECRETS (SMTP / backup creds + passphrase / Paperless token) keep
 * their encrypted casts — those are credentials, not content, and plaintext
 * credentials in a DB dump would be a security downgrade (see the register).
 *
 * Each value is read raw (cast-agnostic) and Crypt::decryptString'd; anything that
 * is not decryptable (already plaintext / null) is left untouched — so the
 * migration is idempotent and a no-op on a fresh install (sqlite tests).
 */
return new class extends Migration
{
    /** table => list of columns that were `encrypted` or `encrypted:array`. */
    private const MAP = [
        'bank_transactions' => ['counterparty', 'counterparty_iban', 'bic', 'purpose', 'booking_text', 'eref', 'receipts'],
        'explore_tracks' => ['points', 'note'],
        'finance_partners' => ['address', 'email', 'phone', 'vat_id', 'contacts'],
        'health_entries' => ['v', 'v2', 'note'],
        'health_profiles' => ['birthdate', 'weight_goal_kg'],
        'health_fasts' => ['note'],
        'invoices' => ['customer', 'lines', 'note', 'versions'],
        'payment_methods' => ['iban', 'bic', 'bank', 'account_no', 'card_number', 'card_expiry', 'card_network', 'paypal_email'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = array_values(array_filter($columns, fn (string $c): bool => Schema::hasColumn($table, $c)));
            if ($cols === []) {
                continue;
            }
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $cols): void {
                foreach ($rows as $row) {
                    $patch = [];
                    foreach ($cols as $col) {
                        $val = $row->{$col} ?? null;
                        if (! is_string($val) || $val === '') {
                            continue;
                        }
                        try {
                            // Laravel-encrypted payloads are base64 JSON envelopes; a plain
                            // value throws DecryptException and is left as-is (idempotent).
                            $patch[$col] = Crypt::decryptString($val);
                        } catch (Throwable) {
                            // already plaintext or not our ciphertext — skip
                        }
                    }
                    if ($patch !== []) {
                        DB::table($table)->where('id', $row->id)->update($patch);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // One-way: re-encryption is not restored (the casts are gone).
    }
};
