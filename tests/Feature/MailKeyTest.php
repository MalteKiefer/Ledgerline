<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\MailPgpKey;
use App\Models\User;
use App\Services\Mail\MaildirIngestor;
use App\Support\BinaryProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MailKeyTest extends TestCase
{
    use RefreshDatabase;

    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->work = sys_get_temp_dir().'/mailkey-'.bin2hex(random_bytes(6));
        @mkdir($this->work.'/cur', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->work);
        parent::tearDown();
    }

    private function account(User $user): MailAccount
    {
        return MailAccount::factory()->create(['user_id' => $user->id]);
    }

    private function ingestRaw(MailAccount $account, string $raw): MailMessage
    {
        $path = $this->work.'/cur/'.bin2hex(random_bytes(4)).':2,S';
        file_put_contents($path, $raw);
        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        return MailMessage::query()->where('user_id', $account->user_id)->latest('created_at')->firstOrFail();
    }

    // ---- CRUD (no crypto binary needed) ----

    public function test_pgp_key_crud_owner_scoped_and_private_never_returned(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson(route('mail.keys.store'), [
            'type' => 'pgp',
            'label' => 'My key',
            'armored_private_key' => "-----BEGIN PGP PRIVATE KEY BLOCK-----\nnot-a-real-key\n-----END PGP PRIVATE KEY BLOCK-----",
            'passphrase' => 'secret-pass',
        ])->assertCreated();

        // No private material in the response.
        $res->assertJsonMissingPath('key.private_key')->assertJsonMissingPath('key.passphrase');
        $this->assertArrayNotHasKey('private_key', $res->json('key'));

        $id = $res->json('key.id');
        $this->getJson(route('mail.keys.index'))->assertOk()->assertJsonCount(1, 'keys')
            ->assertJsonMissingPath('keys.0.private_key');

        // Stored encrypted at rest; decrypts via the cast.
        $key = MailPgpKey::findOrFail($id);
        $this->assertStringContainsString('not-a-real-key', (string) $key->private_key);
        $this->assertNotSame((string) $key->private_key, $key->getRawOriginal('private_key'));

        // Foreign user cannot delete.
        $this->actingAs(User::factory()->create())
            ->deleteJson(route('mail.keys.destroy', $id))->assertNotFound();
        $this->actingAs($user)->deleteJson(route('mail.keys.destroy', $id))->assertNoContent();
        $this->assertDatabaseMissing('mail_pgp_keys', ['id' => $id]);
    }

    public function test_encrypted_message_without_key_is_nokey(): void
    {
        $account = $this->account(User::factory()->create());
        $raw = implode("\r\n", [
            'From: a@example.com', 'Subject: enc', 'Content-Type: text/plain', '',
            '-----BEGIN PGP MESSAGE-----', 'hQEMAdummyciphertext', '-----END PGP MESSAGE-----', '',
        ]);

        $msg = $this->ingestRaw($account, $raw);

        $this->assertSame('pgp', $msg->encrypted_type);
        $this->assertSame('nokey', $msg->decrypt_status);
    }

    // ---- Real PGP decrypt (gated on gpg) ----

    public function test_pgp_decrypt_end_to_end(): void
    {
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available');
        }

        [$armoredSecret, $pgpMessage] = $this->generatePgp('SECRET INVOICE 4242');

        $user = User::factory()->create();
        $account = $this->account($user);

        // Import the key, then ingest an inline-PGP message → decrypt at ingest.
        $this->actingAs($user)->postJson(route('mail.keys.store'), [
            'type' => 'pgp', 'label' => 'Test', 'armored_private_key' => $armoredSecret,
        ])->assertCreated();

        $raw = "From: a@example.com\r\nSubject: enc\r\nContent-Type: text/plain\r\n\r\n".$pgpMessage."\r\n";
        $msg = $this->ingestRaw($account, $raw);

        $this->assertSame('pgp', $msg->encrypted_type);
        $this->assertSame('ok', $msg->decrypt_status);
        $this->assertStringContainsString('SECRET INVOICE 4242', (string) $msg->text_body);
        $this->assertStringContainsString('4242', (string) $msg->search_text);
    }

    public function test_lazy_decrypt_on_read_after_key_added(): void
    {
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available');
        }

        [$armoredSecret, $pgpMessage] = $this->generatePgp('LATER SECRET 999');

        $user = User::factory()->create();
        $account = $this->account($user);

        // Ingest first (no key) → nokey.
        $raw = "From: a@example.com\r\nSubject: enc\r\nContent-Type: text/plain\r\n\r\n".$pgpMessage."\r\n";
        $msg = $this->ingestRaw($account, $raw);
        $this->assertSame('nokey', $msg->decrypt_status);

        // Add the key, then read → lazy decrypt persists the plaintext.
        $this->actingAs($user)->postJson(route('mail.keys.store'), [
            'type' => 'pgp', 'label' => 'Test', 'armored_private_key' => $armoredSecret,
        ])->assertCreated();

        $this->actingAs($user)->getJson(route('mail.messages.show', $msg->id))
            ->assertOk()
            ->assertJsonPath('message.decrypt_status', 'ok');
        $this->assertStringContainsString('LATER SECRET 999', (string) MailMessage::findOrFail($msg->id)->text_body);
    }

    // ---- Real S/MIME decrypt (gated on openssl) ----

    public function test_smime_decrypt_end_to_end(): void
    {
        if (! BinaryProcess::available('openssl')) {
            $this->markTestSkipped('openssl not available');
        }

        [$p12b64, $smimeMessage] = $this->generateSmime('SMIME SECRET 7777');
        if ($p12b64 === null) {
            $this->markTestSkipped('openssl S/MIME generation unsupported on this build');
        }

        $user = User::factory()->create();
        $account = $this->account($user);

        $this->actingAs($user)->postJson(route('mail.keys.store'), [
            'type' => 'smime', 'label' => 'Test', 'p12_base64' => $p12b64, 'passphrase' => 'p12pass',
        ])->assertCreated();

        $msg = $this->ingestRaw($account, $smimeMessage);

        $this->assertSame('smime', $msg->encrypted_type);
        $this->assertSame('ok', $msg->decrypt_status);
        $this->assertStringContainsString('SMIME SECRET 7777', (string) $msg->text_body);
    }

    // ---- generators ----

    /** @return array{0:string, 1:string} [armored secret key, inline PGP message] */
    private function generatePgp(string $body): array
    {
        $home = $this->work.'/gpghome';
        @mkdir($home, 0700, true);
        $base = ['gpg', '--homedir', $home, '--batch', '--no-tty', '--pinentry-mode', 'loopback', '--passphrase', ''];

        $this->sh([...$base, '--quick-generate-key', 'Test User <test@example.com>', 'default', 'default', 'none']);

        $secret = $this->sh([...$base, '--armor', '--export-secret-keys', 'test@example.com'])['out'];

        $plain = $home.'/plain.txt';
        $enc = $home.'/enc.asc';
        file_put_contents($plain, $body);
        $this->sh([...$base, '--trust-model', 'always', '--armor', '--output', $enc, '--encrypt', '-r', 'test@example.com', $plain]);

        return [$secret, (string) file_get_contents($enc)];
    }

    /** @return array{0:?string, 1:string} [base64 PKCS#12 or null, S/MIME message] */
    private function generateSmime(string $body): array
    {
        $d = $this->work.'/smime';
        @mkdir($d, 0700, true);
        $key = $d.'/key.pem';
        $cert = $d.'/cert.pem';
        $p12 = $d.'/bundle.p12';
        $plain = $d.'/plain.txt';
        $enc = $d.'/enc.eml';
        file_put_contents($plain, "Content-Type: text/plain\r\n\r\n".$body."\r\n");

        $this->sh(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-keyout', $key, '-out', $cert,
            '-days', '2', '-nodes', '-subj', '/CN=Test/emailAddress=test@example.com']);
        $this->sh(['openssl', 'pkcs12', '-export', '-inkey', $key, '-in', $cert, '-out', $p12, '-passout', 'pass:p12pass']);
        if (! is_file($p12) || filesize($p12) === 0) {
            return [null, ''];
        }
        $this->sh(['openssl', 'smime', '-encrypt', '-aes-256-cbc', '-in', $plain, '-out', $enc, '-outform', 'SMIME', $cert]);

        $smime = is_file($enc) ? (string) file_get_contents($enc) : '';
        if ($smime === '' || ! str_contains(strtolower($smime), 'pkcs7-mime')) {
            return [null, ''];
        }

        return [base64_encode((string) file_get_contents($p12)), $smime];
    }

    /**
     * @param  list<string>  $argv
     * @return array{out:string, err:string, ok:bool}
     */
    private function sh(array $argv): array
    {
        $p = new Process($argv);
        $p->setTimeout(60);
        $p->run();

        return ['out' => $p->getOutput(), 'err' => $p->getErrorOutput(), 'ok' => $p->isSuccessful()];
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
