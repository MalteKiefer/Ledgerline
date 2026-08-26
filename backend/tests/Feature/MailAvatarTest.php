<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\MailMessage;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A picture for a mail sender, and what it is allowed to cost.
 */
class MailAvatarTest extends TestCase
{
    use RefreshDatabase;

    /** A 1x1 PNG, so the MIME sniff has something real to find. */
    private const PNG = "\x89PNG\r\n\x1a\n".'rest of a png';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function contactWithPhoto(User $user, string $email): void
    {
        // No factory for address books; created directly.
        $book = new AddressBook;
        $book->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Contacts',
            'uri' => 'default',
        ])->save();
        $vcard = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'UID:'.Str::uuid(),
            'FN:Marlene Böhm',
            'EMAIL:'.$email,
            'PHOTO:data:image/png;base64,'.base64_encode(self::PNG),
            'END:VCARD',
            '',
        ]);
        (new Contact)->forceFill([
            'id' => (string) Str::uuid(),
            'address_book_id' => $book->id,
            'uid' => (string) Str::uuid(),
            'uri' => Str::uuid().'.vcf',
            'etag' => Str::random(8),
            'vcard' => $vcard,
            'fn' => 'Marlene Böhm',
            'emails' => [$email],
            'has_photo' => true,
        ])->save();
    }

    public function test_the_address_book_answers_first_and_nothing_leaves_the_machine(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->contactWithPhoto($user, 'marlene@quantco.com');
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'domain'])->save();

        $res = $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['Marlene@QuantCo.com']])
            ->assertOk();

        // Read the map and index it here: json('avatars.a@b.com') would treat
        // the dots in the address as nesting and find nothing.
        $avatars = $res->json('avatars');
        $this->assertIsArray($avatars);
        $this->assertStringStartsWith('data:image/png;base64,', (string) ($avatars['marlene@quantco.com'] ?? ''));
        // The address book had the answer, so no domain was looked up.
        Http::assertNothingSent();
    }

    public function test_off_looks_nothing_up_at_all(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->contactWithPhoto($user, 'marlene@quantco.com');
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'off'])->save();

        $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['marlene@quantco.com']])
            ->assertOk()
            ->assertJsonPath('avatars', []);

        Http::assertNothingSent();
    }

    public function test_contacts_mode_never_touches_the_network(): void
    {
        // The default, and the whole point of it: an unknown sender gets no
        // picture rather than a request to a third party.
        Http::fake();
        $user = User::factory()->create();
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'contacts'])->save();

        $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['stranger@example.com']])
            ->assertOk()
            ->assertJsonPath('avatars', []);

        Http::assertNothingSent();
    }

    public function test_domain_mode_falls_back_to_the_company_mark(): void
    {
        $user = User::factory()->create();
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'domain'])->save();
        Http::fake(['*' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png'])]);

        $res = $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['someone@quantco.com']])
            ->assertOk();

        $avatars = $res->json('avatars');
        $this->assertIsArray($avatars);
        $this->assertStringStartsWith('data:image/png;base64,', (string) ($avatars['someone@quantco.com'] ?? ''));
    }

    public function test_an_alias_service_does_not_get_credit_for_the_real_sender(): void
    {
        // Mail forwarded by SimpleLogin arrives from
        // …_at_twitch_tv_xxxx@simplelogin.co, so the address' own domain would
        // return SimpleLogin's mark. The real sender is in the display name,
        // which the service fills in deliberately as a readable address.
        $user = User::factory()->create();
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'domain'])->save();

        (new MailMessage)->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'folder' => 'INBOX',
            'from_email' => 'purchase-noreply_at_twitch_tv_xkdb@simplelogin.co',
            'from_name' => 'purchase-noreply@twitch.tv',
            'content_hash' => hash('sha256', 'alias'),
            'size' => 1,
            'created_at' => now(),
        ])->save();

        $asked = [];
        Http::fake(function ($request) use (&$asked) {
            $asked[] = (string) $request->url();

            return Http::response(self::PNG, 200, ['Content-Type' => 'image/png']);
        });

        $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['purchase-noreply_at_twitch_tv_xkdb@simplelogin.co']])
            ->assertOk();

        // twitch.tv is asked; simplelogin.co is not reached at all, because the
        // first rung already answered.
        $this->assertNotEmpty($asked);
        $this->assertStringContainsString('twitch.tv', implode(' ', $asked));
        $this->assertStringNotContainsString('simplelogin.co', implode(' ', $asked));
    }

    public function test_it_never_reads_another_users_address_book(): void
    {
        Http::fake();
        $mine = User::factory()->create();
        $this->contactWithPhoto(User::factory()->create(), 'marlene@quantco.com');
        UserSetting::for((int) $mine->id)->forceFill(['mail_avatars' => 'contacts'])->save();

        $this->actingAs($mine)
            ->postJson('/mail/avatars', ['emails' => ['marlene@quantco.com']])
            ->assertOk()
            ->assertJsonPath('avatars', []);
    }

    public function test_a_partial_address_is_not_a_match(): void
    {
        // The database pre-filter is a LIKE over the JSON column; the exact
        // comparison has to happen after it, or a@b.com would match ma@b.com.
        Http::fake();
        $user = User::factory()->create();
        $this->contactWithPhoto($user, 'marlene@quantco.com');
        UserSetting::for((int) $user->id)->forceFill(['mail_avatars' => 'contacts'])->save();

        $this->actingAs($user)
            ->postJson('/mail/avatars', ['emails' => ['arlene@quantco.com']])
            ->assertOk()
            ->assertJsonPath('avatars', []);
    }
}
