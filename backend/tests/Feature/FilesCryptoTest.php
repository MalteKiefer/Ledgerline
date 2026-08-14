<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CryptoRecipient;
use App\Models\FileEntry;
use App\Models\MailPgpKey;
use App\Models\User;
use App\Support\BinaryProcess;
use App\Support\BlobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesCryptoTest extends TestCase
{
    use RefreshDatabase;

    // Static passphrase-less test keypairs (base64 of armored PGP), so tests are
    // deterministic and don't depend on live gpg key generation (flaky under a
    // shared local gpg-agent). Base64 keeps the PGP header out of the source (gitleaks).
    private const OWN_PUB = 'LS0tLS1CRUdJTiBQR1AgUFVCTElDIEtFWSBCTE9DSy0tLS0tCgptRE1FYW43UWxCWUpLd1lCQkFIYVJ3OEJBUWRBdDkzdUFzWm84MUgveXpvWGJlczMwSWk5YUlGbnpSVHZjTS84CkJMSkRyZmUwRVZSamF6RWdQR05yTVVCbExuUmxjM1EraUs4RUV4WUtBRmNXSVFSVTJwOVI3Yi9RNy9FRlBlUzAKMytvRnpuemxGQVVDYW43UWxCc1VnQUFBQUFBRUFBNXRZVzUxTWl3eUxqVXJNUzR4TWl3d0xETUNHd01GQ3drSQpCd0lDSWdJR0ZRb0pDQXNDQkJZQ0F3RUNIZ2NDRjRBQUNna1F0Ti9xQmM1ODVSVGNWZ0QrUDAveTBWdGh3Wm9uCjJqVlI3NXJBdE1DSUp4UTBOZlgydDNmR011d3MyMGNBLzF6TU9pV1V6dEtVT1o5SUNVZW92cXJkYVo3elZOZGgKS3Q4RTJFNWpBVUlPdURnRWFuN1FsQklLS3dZQkJBR1hWUUVGQVFFSFFORUNmeVVSczFxMmRzakZlU1NIQjJ0Zgp2eEsyUnZIekRFR2syWmRiZ1p3RUF3RUlCNGlVQkJnV0NnQThGaUVFVk5xZlVlMi8wTy94QlQza3ROL3FCYzU4CjVSUUZBbXArMEpRYkZJQUFBQUFBQkFBT2JXRnVkVElzTWk0MUt6RXVNVElzTUN3ekFoc01BQW9KRUxUZjZnWE8KZk9VVVFiWUErZ0l4SkNWdFpHTnhHS2lFMVV2QVduTXhRN1kvRUdlZUloaWRjUTRDV25PMkFQOXlmcndrZjBXNgpjeUFMZXJnajRGQi9uM1BRU2Irc3BTakNyT1FoM1BzbUJnPT0KPWhxVmgKLS0tLS1FTkQgUEdQIFBVQkxJQyBLRVkgQkxPQ0stLS0tLQo=';

    private const OWN_SEC = 'LS0tLS1CRUdJTiBQR1AgUFJJVkFURSBLRVkgQkxPQ0stLS0tLQoKbEZnRWFuN1FsQllKS3dZQkJBSGFSdzhCQVFkQXQ5M3VBc1pvODFIL3l6b1hiZXMzMElpOWFJRm56UlR2Y00vOApCTEpEcmZjQUFQNGtlcU8wbFZOalV6WVA0YnRQV1ZRaWY4RFFVbURZam1tYytHVStzbXQvOHhEbXRCRlVZMnN4CklEeGphekZBWlM1MFpYTjBQb2l2QkJNV0NnQlhGaUVFVk5xZlVlMi8wTy94QlQza3ROL3FCYzU4NVJRRkFtcCsKMEpRYkZJQUFBQUFBQkFBT2JXRnVkVElzTWk0MUt6RXVNVElzTUN3ekFoc0RCUXNKQ0FjQ0FpSUNCaFVLQ1FnTApBZ1FXQWdNQkFoNEhBaGVBQUFvSkVMVGY2Z1hPZk9VVTNGWUEvajlQOHRGYlljR2FKOW8xVWUrYXdMVEFpQ2NVCk5EWDE5cmQzeGpMc0xOdEhBUDljekRvbGxNN1NsRG1mU0FsSHFMNnEzV21lODFUWFlTcmZCTmhPWXdGQ0RweGQKQkdwKzBKUVNDaXNHQVFRQmwxVUJCUUVCQjBEUkFuOGxFYk5hdG5iSXhYa2tod2RyWDc4U3RrYng4d3hCcE5tWApXNEdjQkFNQkNBY0FBUDl5ajJCUUdRclZCZm0vT3dhZHAvaFN4THkxNjltckoxOXNvK2hKa0ZET01CRjhpSlFFCkdCWUtBRHdXSVFSVTJwOVI3Yi9RNy9FRlBlUzAzK29Gem56bEZBVUNhbjdRbEJzVWdBQUFBQUFFQUE1dFlXNTEKTWl3eUxqVXJNUzR4TWl3d0xETUNHd3dBQ2drUXROL3FCYzU4NVJSQnRnRDZBakVrSlcxa1kzRVlxSVRWUzhCYQpjekZEdGo4UVo1NGlHSjF4RGdKYWM3WUEvM0ordkNSL1JicHpJQXQ2dUNQZ1VIK2ZjOUJKdjZ5bEtNS3M1Q0hjCit5WUcKPUtuUEUKLS0tLS1FTkQgUEdQIFBSSVZBVEUgS0VZIEJMT0NLLS0tLS0K';

    private const BOB_PUB = 'LS0tLS1CRUdJTiBQR1AgUFVCTElDIEtFWSBCTE9DSy0tLS0tCgptRE1FYW43UWxoWUpLd1lCQkFIYVJ3OEJBUWRBcFJ1eXFaZ2NOeTdmMzZvdVIvclI5bVI5VFNlOVRIbksrMlY0Ck9VT0dNbWUwRVZSamF6SWdQR05yTWtCbExuUmxjM1EraUs4RUV4WUtBRmNXSVFTNHJmWGhHWjcwZ2xINkE0SkMKd0Z1V2E5eFA4d1VDYW43UWxoc1VnQUFBQUFBRUFBNXRZVzUxTWl3eUxqVXJNUzR4TWl3d0xETUNHd01GQ3drSQpCd0lDSWdJR0ZRb0pDQXNDQkJZQ0F3RUNIZ2NDRjRBQUNna1FRc0JibG12Y1QvUHYzUUVBL1drZWtsZjZ4YllXCkFFc054L1g0aG5iVHd0Rmo5Y1lzRWdsOU5FRHcvZGdCQUpLK1FReVYrcVJBSEVvdVdkVjlDWTRyQ01mT0JzNzMKZytPNWlFdEYrcHNHdURnRWFuN1FsaElLS3dZQkJBR1hWUUVGQVFFSFFKd2xWQ0Rud0ZxL2REMU9NSHJVVzhDbApaRllkSS9tY0FMaVpGVm9OeEdRY0F3RUlCNGlVQkJnV0NnQThGaUVFdUszMTRSbWU5SUpSK2dPQ1FzQmJsbXZjClQvTUZBbXArMEpZYkZJQUFBQUFBQkFBT2JXRnVkVElzTWk0MUt6RXVNVElzTUN3ekFoc01BQW9KRUVMQVc1WnIKM0UvekJwTUEvaXVjNTMvSytMaFo4a1kyNFFKYmI4TjdTM2w3L2dTR3FOaHpBZ3RrVGhLR0FRQ2x6MXpJN1JnOQp2MTh0U3NBajdzaTJQeUl3d2h5WUsvQ0E0VmpaUGJlekN3PT0KPVFVTU0KLS0tLS1FTkQgUEdQIFBVQkxJQyBLRVkgQkxPQ0stLS0tLQo=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available.');
        }
    }

    /** @return array{0:string,1:string} [armoredPublic, armoredSecret] */
    private function genKeypair(string $which): array
    {
        return str_contains(strtolower($which), 'bob')
            ? [base64_decode(self::BOB_PUB), '']
            : [base64_decode(self::OWN_PUB), base64_decode(self::OWN_SEC)];
    }

    private function putFile(User $u, string $name, string $body): FileEntry
    {
        $path = 'files/'.Str::uuid();
        BlobStore::disk()->put($path, $body);
        $f = new FileEntry;
        $f->forceFill(['user_id' => $u->id, 'file_folder_id' => null, 'name' => $name, 'storage_path' => $path, 'size' => strlen($body)])->save();

        return $f;
    }

    private function ownKey(User $u): MailPgpKey
    {
        [$pub, $sec] = $this->genKeypair('Owner <owner@example.com>');
        $k = new MailPgpKey;
        $k->forceFill(['user_id' => $u->id, 'type' => 'pgp', 'label' => 'mine', 'public_key' => $pub, 'private_key' => $sec, 'passphrase' => ''])->save();

        return $k;
    }

    public function test_pgp_encrypt_then_decrypt_round_trips(): void
    {
        $u = User::factory()->create();
        $key = $this->ownKey($u);
        $doc = $this->putFile($u, 'secret.txt', 'CLASSIFIED');

        $enc = $this->actingAs($u)->postJson(route('files.encrypt', ['file' => $doc->id]), ['key_id' => $key->id])->assertOk();
        $encFile = FileEntry::findOrFail($enc->json('file.id'));
        $this->assertStringEndsWith('.gpg', (string) $encFile->name);
        $cipher = BlobStore::disk()->get((string) $encFile->storage_path);
        $this->assertStringNotContainsString('CLASSIFIED', $cipher); // actually encrypted

        $dec = $this->actingAs($u)->postJson(route('files.decrypt', ['file' => $encFile->id]), ['key_id' => $key->id])->assertOk();
        $decFile = FileEntry::findOrFail($dec->json('file.id'));
        $this->assertSame('secret.txt', $decFile->name);
        $this->assertSame('CLASSIFIED', BlobStore::disk()->get((string) $decFile->storage_path));
    }

    public function test_encrypt_to_a_recipient_and_owner_can_still_decrypt(): void
    {
        $u = User::factory()->create();
        $key = $this->ownKey($u);
        [$rpub] = $this->genKeypair('Bob <bob@example.com>');
        $rcpt = new CryptoRecipient;
        $rcpt->forceFill(['user_id' => $u->id, 'type' => 'pgp', 'label' => 'Bob', 'public_key' => $rpub])->save();
        $doc = $this->putFile($u, 'note.txt', 'HELLO-BOB');

        $enc = $this->actingAs($u)->postJson(route('files.encrypt', ['file' => $doc->id]), ['key_id' => $key->id, 'recipient_ids' => [$rcpt->id]])->assertOk();
        $encFile = FileEntry::findOrFail($enc->json('file.id'));

        // Encrypted to Bob AND self → the owner can decrypt with their own key.
        $dec = $this->actingAs($u)->postJson(route('files.decrypt', ['file' => $encFile->id]), ['key_id' => $key->id])->assertOk();
        $this->assertSame('HELLO-BOB', BlobStore::disk()->get((string) FileEntry::findOrFail($dec->json('file.id'))->storage_path));
    }

    public function test_decrypt_rejects_non_encrypted_file(): void
    {
        $u = User::factory()->create();
        $key = $this->ownKey($u);
        $doc = $this->putFile($u, 'plain.txt', 'x');
        $this->actingAs($u)->postJson(route('files.decrypt', ['file' => $doc->id]), ['key_id' => $key->id])->assertStatus(422);
    }

    public function test_keyring_lists_own_keys_and_recipients_import(): void
    {
        $u = User::factory()->create();
        $key = $this->ownKey($u);
        // Import a valid armored public key as a recipient (reuse the own key's public).
        $add = $this->actingAs($u)->postJson(route('crypto.recipients.store'), ['type' => 'pgp', 'label' => 'Carol', 'material' => (string) $key->public_key])->assertOk();
        $this->assertNotEmpty($add->json('recipient.fingerprint'));

        $ring = $this->actingAs($u)->getJson(route('crypto.keyring'))->assertOk();
        $this->assertCount(1, $ring->json('keys'));
        $this->assertTrue($ring->json('keys.0.has_private'));
        $this->assertCount(1, $ring->json('recipients'));
    }
}
