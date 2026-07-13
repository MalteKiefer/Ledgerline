<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecryptEncryptedColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function encrypter(string $cipher): Encrypter
    {
        $appKey = (string) config('app.key');
        $key = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7))
            : $appKey;

        return new Encrypter($key, $cipher);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_22_100000_recrypt_encrypted_columns_to_gcm.php');
    }

    public function test_it_reencrypts_cbc_values_to_gcm_without_data_loss(): void
    {
        $cbc = $this->encrypter('AES-256-CBC');
        $gcm = $this->encrypter('AES-256-GCM');

        // A row created with the old (CBC) cipher: seed the raw column directly so
        // the value is genuinely CBC-encrypted, bypassing the model cast.
        $settings = AppSettings::create();
        $secret = 'smtp-p@ssw0rd!';
        DB::table('app_settings')->where('id', $settings->id)
            ->update(['smtp_host' => $cbc->encrypt($secret, false)]);

        $this->migration()->up();

        // The stored value is now GCM and still decrypts to the exact plaintext.
        $raw = DB::table('app_settings')->where('id', $settings->id)->value('smtp_host');
        $this->assertSame($secret, $gcm->decrypt($raw, false));

        // And it is no longer decryptable as CBC — i.e. it really was rewritten.
        $this->expectException(DecryptException::class);
        $cbc->decrypt($raw, false);
    }

    public function test_it_is_idempotent_and_skips_already_gcm_values(): void
    {
        $gcm = $this->encrypter('AES-256-GCM');

        $settings = AppSettings::create();
        $secret = 'ntfy-token-xyz';
        DB::table('app_settings')->where('id', $settings->id)
            ->update(['ntfy_token' => $gcm->encrypt($secret, false)]);

        // Running up() over a value that is already GCM must leave it intact
        // (it does not decrypt under the CBC source cipher, so it is skipped).
        $this->migration()->up();

        $raw = DB::table('app_settings')->where('id', $settings->id)->value('ntfy_token');
        $this->assertSame($secret, $gcm->decrypt($raw, false));
    }

    public function test_it_leaves_null_encrypted_columns_untouched(): void
    {
        $settings = AppSettings::create(); // all encrypted columns null

        $this->migration()->up();

        $this->assertNull(DB::table('app_settings')->where('id', $settings->id)->value('smtp_password'));
    }
}
