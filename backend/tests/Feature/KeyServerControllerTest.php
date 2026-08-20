<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CryptoRecipient;
use App\Models\KeyServer;
use App\Models\User;
use App\Support\BinaryProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * App\Http\Controllers\KeyServerController::refreshRecipient — both refresh
 * paths: a keyserver-imported recipient (known origin, key_server_id set)
 * asks exactly that server; a manually-pasted one (no origin) searches every
 * enabled server instead and adopts whichever one verifiably has the exact
 * fingerprint. Real gpg is used to produce one genuine armored public key +
 * fingerprint (fetch() cryptographically re-derives it from whatever a
 * server returns, so a stub/fake key wouldn't exercise that check).
 */
class KeyServerControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{public_key: string, fingerprint: string} */
    private function realPgpPublicKey(): array
    {
        $owner = User::factory()->create();
        $res = $this->actingAs($owner)->postJson(route('crypto.keys.generate'), [
            'type' => 'pgp',
            'label' => 'Keyserver fixture',
            'identities' => [['email' => 'fixture@example.com']],
        ])->assertCreated();

        return [
            'public_key' => (string) $res->json('key.public_key'),
            'fingerprint' => (string) $res->json('key.fingerprint'),
        ];
    }

    private function manualRecipient(User $user, string $armored, string $label = 'Manual'): CryptoRecipient
    {
        // The endpoint answers 200 with the saved recipient (see openapi.yaml
        // cryptoRecipientStore); it is not a 201-with-Location resource.
        $res = $this->actingAs($user)->postJson(route('crypto.recipients.store'), [
            'type' => 'pgp', 'label' => $label, 'material' => $armored,
        ])->assertOk();

        return CryptoRecipient::findOrFail($res->json('recipient.id'));
    }

    private function server(User $user, string $url, bool $enabled = true): KeyServer
    {
        $server = new KeyServer;
        $server->forceFill(['user_id' => $user->id, 'name' => $url, 'url' => $url, 'enabled' => $enabled])->save();

        return $server;
    }

    // ---- No real gpg needed: pure validation/routing, no HkpClient call ----

    public function test_refresh_refuses_a_recipient_with_no_fingerprint_to_search_by(): void
    {
        $user = User::factory()->create();
        $recipient = new CryptoRecipient;
        $recipient->forceFill(['user_id' => $user->id, 'type' => 'smime', 'label' => 'Cert', 'cert_pem' => "-----BEGIN CERTIFICATE-----\nfake\n-----END CERTIFICATE-----"])->save();

        $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))
            ->assertStatus(422)->assertJson(['error' => 'no_origin_server']);
    }

    public function test_refresh_reports_no_servers_when_none_are_configured(): void
    {
        $user = User::factory()->create();
        $recipient = new CryptoRecipient;
        $recipient->forceFill(['user_id' => $user->id, 'type' => 'pgp', 'label' => 'X', 'fingerprint' => str_repeat('AB', 20)])->save();

        $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))
            ->assertStatus(422)->assertJson(['error' => 'no_servers']);
    }

    public function test_refresh_only_searches_this_users_own_enabled_servers(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->server($stranger, 'https://not-mine.example');
        $this->server($user, 'https://mine.example', enabled: false);

        $recipient = new CryptoRecipient;
        $recipient->forceFill(['user_id' => $user->id, 'type' => 'pgp', 'label' => 'X', 'fingerprint' => str_repeat('AB', 20)])->save();

        // Neither a foreign user's server nor this user's own DISABLED one
        // should ever be visited — no_servers, not a network attempt.
        Http::fake(fn () => Http::response('should never be called', 500));
        $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))
            ->assertStatus(422)->assertJson(['error' => 'no_servers']);
        Http::assertNothingSent();
    }

    // ---- Real gpg: fetch() cryptographically re-verifies the server's answer ----

    public function test_manual_recipient_refresh_falls_back_to_searching_every_enabled_server(): void
    {
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available');
        }
        ['public_key' => $armored, 'fingerprint' => $fingerprint] = $this->realPgpPublicKey();

        $user = User::factory()->create();
        $recipient = $this->manualRecipient($user, $armored);
        $this->assertNull($recipient->key_server_id, 'manually-pasted recipients start with no known origin');

        $miss = $this->server($user, 'https://miss.example');
        $hit = $this->server($user, 'https://hit.example');

        Http::fake([
            'https://miss.example/*' => Http::response('', 404),
            'https://hit.example/*' => Http::response($armored, 200),
        ]);

        $res = $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))->assertOk();
        $this->assertSame($hit->id, $res->json('recipient.key_server_id'), 'adopts the server that actually verified the key, not the one that missed');
        $this->assertNotNull($res->json('recipient.refreshed_at'));

        $recipient->refresh();
        $this->assertSame($hit->id, $recipient->key_server_id);
        $this->assertSame($fingerprint, $recipient->fingerprint, 'fingerprint is unchanged — only public_key/key_server_id/refreshed_at move');
    }

    public function test_manual_recipient_refresh_404s_when_no_server_has_it(): void
    {
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available');
        }
        ['public_key' => $armored] = $this->realPgpPublicKey();

        $user = User::factory()->create();
        $recipient = $this->manualRecipient($user, $armored);
        $this->server($user, 'https://a.example');
        $this->server($user, 'https://b.example');

        Http::fake(fn () => Http::response('', 404));

        $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))
            ->assertStatus(404)->assertJson(['error' => 'not_found']);
        $this->assertNull($recipient->fresh()->key_server_id, 'a miss on every server must not adopt one');
    }

    public function test_known_origin_recipient_asks_only_that_server_and_rejects_a_mismatched_reply(): void
    {
        if (! BinaryProcess::available('gpg')) {
            $this->markTestSkipped('gpg not available');
        }
        ['public_key' => $armoredA] = $this->realPgpPublicKey();
        ['public_key' => $armoredB] = $this->realPgpPublicKey(); // a different real key

        $user = User::factory()->create();
        $recipient = $this->manualRecipient($user, $armoredA);
        $origin = $this->server($user, 'https://origin.example');
        $recipient->forceFill(['key_server_id' => $origin->id])->save();
        // A second, enabled server that would happily answer if the
        // known-origin path incorrectly fanned out to it too.
        $this->server($user, 'https://other.example');

        // The origin server misbehaves/is compromised and returns a
        // DIFFERENT (but perfectly validly-formed) key for the same lookup.
        Http::fake([
            'https://origin.example/*' => Http::response($armoredB, 200),
            'https://other.example/*' => Http::response('should never be called', 500),
        ]);

        $this->actingAs($user)->postJson(route('crypto.recipients.refresh', $recipient->id))
            ->assertStatus(422)->assertJson(['error' => 'fingerprint_mismatch']);
        Http::assertNotSent(fn ($req) => str_starts_with((string) $req->url(), 'https://other.example'));
        $this->assertStringContainsString($armoredA, (string) $recipient->fresh()->public_key, 'never swapped in the mismatched key');
    }
}
