<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Mail\ComposedMessage;
use App\Services\Mail\MailSender;
use App\Services\Mail\SendResult;
use App\Support\Mail\ImapAppender;
use App\Support\Mail\SmtpProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * SMTP send / reply / forward (design §2.7 item 8). Controller tests bind a spy
 * MailSender to capture the ComposedMessage the controller builds (subject
 * prefixing, quoting, recipient resolution, attachments, signature) without any
 * SMTP socket; a MailSender unit test drives a real array-transport send + a
 * spy ImapAppender to prove the Sent-append + runtime-mailer teardown.
 */
class MailSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function account(User $user, array $overrides = []): MailAccount
    {
        return MailAccount::factory()->create(array_merge([
            'user_id' => $user->id,
            'host' => 'imap.example.invalid',
            'encryption' => 'ssl',
            'username' => 'me@example.com',
            'password' => 'imap-secret',
            'smtp_host' => 'smtp.example.invalid',
            'smtp_port' => 587,
            'smtp_username' => 'me@example.com',
            'smtp_password' => 'smtp-secret',
            'smtp_encryption' => 'starttls',
            'from_name' => 'Me',
            'from_email' => 'me@example.com',
        ], $overrides));
    }

    private function message(MailAccount $account, array $attrs = []): MailMessage
    {
        $id = (string) Str::uuid();
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => $id,
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', $id),
            'size' => 20,
            'message_id' => '<orig-'.$id.'@example.com>',
            'subject' => 'Project update',
            'from_name' => 'Alice',
            'from_email' => 'alice@example.com',
            'reply_to' => null,
            'to_json' => [['name' => 'Me', 'email' => 'me@example.com'], ['name' => null, 'email' => 'team@example.com']],
            'cc_json' => [['name' => null, 'email' => 'boss@example.com']],
            'text_body' => "Original line one\nOriginal line two",
            'seen' => true,
            'created_at' => now(),
        ], $attrs))->save();

        return $m;
    }

    /** Bind a spy MailSender and return it; captures the ComposedMessage. */
    private function spySender(): object
    {
        $spy = new class(app(ImapAppender::class)) extends MailSender
        {
            public ?ComposedMessage $captured = null;

            public ?MailAccount $capturedAccount = null;

            public function send(MailAccount $account, ComposedMessage $message): SendResult
            {
                $this->captured = $message;
                $this->capturedAccount = $account;

                return new SendResult('<generated@example.com>', true);
            }
        };
        $this->app->instance(MailSender::class, $spy);

        return $spy;
    }

    // ---- compose ----

    public function test_compose_sends_and_builds_recipients_and_body(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $spy = $this->spySender();

        $this->actingAs($user)
            ->postJson(route('mail.messages.compose'), [
                'account_id' => $account->id,
                'to' => ['dest@example.com'],
                'cc' => ['cc@example.com'],
                'subject' => 'Hello there',
                'text' => 'A composed body',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'message_id' => '<generated@example.com>', 'appended_to_sent' => true]);

        $this->assertNotNull($spy->captured);
        $this->assertSame('Hello there', $spy->captured->subject);
        $this->assertSame([['name' => null, 'email' => 'dest@example.com']], $spy->captured->to);
        $this->assertSame([['name' => null, 'email' => 'cc@example.com']], $spy->captured->cc);
        $this->assertSame('A composed body', $spy->captured->text);
        $this->assertSame($account->id, $spy->capturedAccount->id);
    }

    public function test_compose_appends_signature(): void
    {
        $user = User::factory()->create();
        UserSetting::for($user->id)->update(['mail_signature' => 'Cheers,\nMe']);
        $account = $this->account($user);
        $spy = $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id,
            'to' => ['dest@example.com'],
            'subject' => 'S',
            'text' => 'Body',
        ])->assertOk();

        $this->assertStringContainsString('-- ', (string) $spy->captured->text);
        $this->assertStringContainsString('Cheers,', (string) $spy->captured->text);
    }

    public function test_compose_requires_recipient_and_body(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id, 'subject' => 'S', 'text' => 'B',
        ])->assertStatus(422)->assertJson(['error' => 'no_recipient']);

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id, 'to' => ['x@y.z'], 'subject' => 'S',
        ])->assertStatus(422)->assertJson(['error' => 'empty_body']);
    }

    public function test_compose_without_smtp_is_no_smtp(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, ['smtp_host' => null, 'from_email' => null]);
        $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id, 'to' => ['x@y.z'], 'subject' => 'S', 'text' => 'B',
        ])->assertStatus(422)->assertJson(['error' => 'no_smtp']);
    }

    public function test_compose_foreign_account_is_404(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $this->spySender();

        $this->actingAs(User::factory()->create())->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id, 'to' => ['x@y.z'], 'subject' => 'S', 'text' => 'B',
        ])->assertNotFound();
    }

    public function test_compose_attaches_uploaded_and_referenced_attachments(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account);
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('mail/att/'.$blob, 'STORED-BYTES');
        (new MailBlob)->forceFill(['blob' => $blob, 'user_id' => $user->id, 'kind' => 'attachment', 'size' => 12, 'created_at' => now()])->save();
        $att = new MailAttachment;
        $att->forceFill([
            'id' => (string) Str::uuid(), 'message_id' => $msg->id, 'user_id' => $user->id, 'blob' => $blob,
            'filename' => 'report.pdf', 'content_type' => 'application/pdf', 'inline' => false, 'size' => 12, 'created_at' => now(),
        ])->save();
        $spy = $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id,
            'to' => ['dest@example.com'],
            'subject' => 'With files',
            'text' => 'see attached',
            'attachment_ids' => [$att->id],
        ])->assertOk();

        $this->assertCount(1, $spy->captured->attachments);
        $this->assertSame('report.pdf', $spy->captured->attachments[0]['filename']);
        $this->assertSame('STORED-BYTES', $spy->captured->attachments[0]['bytes']);
    }

    public function test_compose_attaches_owned_files_and_gallery_media(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $file = new FileEntry;
        $file->forceFill(['user_id' => $user->id, 'name' => 'brief.pdf', 'mime' => 'application/pdf', 'size' => 4, 'storage_path' => 'files/brief', 'version' => 1])->save();
        $photo = new GalleryPhoto;
        $photo->forceFill(['user_id' => $user->id, 'name' => 'photo.jpg', 'mime' => 'image/jpeg', 'media_type' => 'image', 'status' => 'ready', 'size' => 5, 'storage_path' => 'gallery/photo', 'version' => 1])->save();
        Storage::disk(config('files.disk'))->put('files/brief', 'FILE');
        Storage::disk(config('files.disk'))->put('gallery/photo', 'PHOTO');
        $spy = $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.compose'), [
            'account_id' => $account->id, 'to' => ['dest@example.com'], 'text' => 'see attached',
            'file_ids' => [$file->id], 'gallery_photo_ids' => [$photo->id], 'read_receipt' => true, 'high_priority' => true,
        ])->assertOk();

        $this->assertCount(2, $spy->captured->attachments);
        $this->assertSame(['brief.pdf', 'photo.jpg'], array_column($spy->captured->attachments, 'filename'));
        $this->assertTrue($spy->captured->readReceipt);
        $this->assertTrue($spy->captured->highPriority);
    }

    // ---- reply ----

    public function test_reply_quotes_original_and_threads(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account);
        $spy = $this->spySender();

        $this->actingAs($user)
            ->postJson(route('mail.messages.reply', $msg->id), ['text' => 'My reply'])
            ->assertOk();

        $c = $spy->captured;
        $this->assertSame('Re: Project update', $c->subject);
        $this->assertSame([['name' => null, 'email' => 'alice@example.com']], $c->to); // reply to From
        $this->assertStringContainsString('My reply', (string) $c->text);
        $this->assertStringContainsString('> Original line one', (string) $c->text);
        $this->assertSame($msg->message_id, $c->inReplyTo);
        $this->assertContains($msg->message_id, $c->references);
        $this->assertSame([], $c->cc);
    }

    public function test_reply_all_adds_other_recipients_excluding_self(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user); // from_email me@example.com
        $msg = $this->message($account);
        $spy = $this->spySender();

        $this->actingAs($user)
            ->postJson(route('mail.messages.reply', $msg->id), ['text' => 'r', 'all' => true])
            ->assertOk();

        $ccEmails = array_column($spy->captured->cc, 'email');
        $this->assertContains('team@example.com', $ccEmails);
        $this->assertContains('boss@example.com', $ccEmails);
        $this->assertNotContains('me@example.com', $ccEmails); // our own address dropped
        $this->assertNotContains('alice@example.com', $ccEmails); // primary recipient not duplicated
    }

    public function test_reply_prefers_reply_to_header(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account, ['reply_to' => 'noreply-list@example.com']);
        $spy = $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.reply', $msg->id), ['text' => 'r'])->assertOk();
        $this->assertSame('noreply-list@example.com', $spy->captured->to[0]['email']);
    }

    public function test_reply_when_account_deleted_is_422(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account);
        $account->delete(); // account_id nullOnDelete — archive kept
        $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.reply', $msg->fresh()->id), ['text' => 'r'])
            ->assertStatus(422)->assertJson(['error' => 'account_deleted']);
    }

    // ---- forward ----

    public function test_forward_attaches_original_eml_with_fwd_subject(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account);
        Storage::disk(config('files.disk'))->put('mail/'.$msg->id, "Subject: Project update\r\n\r\nOriginal line one");
        $spy = $this->spySender();

        $this->actingAs($user)
            ->postJson(route('mail.messages.forward', $msg->id), ['to' => ['fwd@example.com'], 'text' => 'fyi'])
            ->assertOk();

        $c = $spy->captured;
        $this->assertSame('Fwd: Project update', $c->subject);
        $this->assertSame('fwd@example.com', $c->to[0]['email']);
        $this->assertStringContainsString('Forwarded message', (string) $c->text);
        $this->assertCount(1, $c->attachments);
        $this->assertSame('message/rfc822', $c->attachments[0]['mime']);
        $this->assertStringContainsString('Original line one', $c->attachments[0]['bytes']);
    }

    public function test_forward_requires_recipient(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $msg = $this->message($account);
        $this->spySender();

        $this->actingAs($user)->postJson(route('mail.messages.forward', $msg->id), ['text' => 'x'])
            ->assertStatus(422);
    }

    // ---- MailSender unit: real send + Sent-append + teardown ----

    public function test_mail_sender_sends_and_appends_to_sent_and_scrubs_config(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        $appender = new class extends ImapAppender
        {
            public ?string $folder = null;

            public ?string $raw = null;

            public ?bool $seen = null;

            public function append(MailAccount $account, string $folder, string $rawMessage, string $password, bool $seen = false): void
            {
                $this->folder = $folder;
                $this->raw = $rawMessage;
                $this->seen = $seen;
            }
        };

        // Point the per-account runtime mailer at the in-memory array transport
        // so a real send happens with no SMTP socket.
        $sender = new class($appender) extends MailSender
        {
            protected function configureMailer(string $name, MailAccount $account, string $host, string $fromEmail, ?string $fromName, string $password): void
            {
                config([
                    "mail.mailers.{$name}" => ['transport' => 'array'],
                    "mail.from.{$name}" => ['address' => $fromEmail, 'name' => $fromName ?? $fromEmail],
                ]);
            }
        };

        $composed = new ComposedMessage(
            subject: 'Unit subject',
            text: 'Body with & <chars> {{7*7}}',
            html: null,
            fromEmail: 'me@example.com',
            fromName: 'Me',
            to: [['name' => null, 'email' => 'dest@example.com']],
        );

        $result = $sender->send($account, $composed);

        $this->assertTrue($result->appendedToSent);
        $this->assertStringStartsWith('<', $result->messageId);

        // The sent wire message carries the raw body verbatim (no Blade/SSTI).
        $arr = Mail::mailer('mail_send_'.$account->id)->getSymfonyTransport()->messages();
        $sentMsg = $arr[count($arr) - 1]->getOriginalMessage();
        $this->assertSame('Unit subject', $sentMsg->getSubject());
        $this->assertStringContainsString('{{7*7}}', (string) $sentMsg->getTextBody());

        // Sent-append happened over IMAP, flagged \Seen, to the Sent folder.
        $this->assertSame('Sent', $appender->folder);
        $this->assertTrue($appender->seen);
        $this->assertStringContainsString('Unit subject', (string) $appender->raw);

        // Runtime mailer config is torn down (SMTP creds do not linger).
        $this->assertNull(config('mail.mailers.mail_send_'.$account->id));
    }

    public function test_mail_sender_refuses_ssrf_blocked_smtp_host(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, ['smtp_host' => '169.254.169.254']);

        $this->expectException(RuntimeException::class);
        app(MailSender::class)->send($account, new ComposedMessage(
            subject: 'x', text: 'y', html: null,
            fromEmail: 'me@example.com', fromName: null,
            to: [['name' => null, 'email' => 'd@e.f']],
        ));
    }

    // ---- SmtpProbe ----

    public function test_smtp_probe_ssrf_and_missing_host(): void
    {
        $user = User::factory()->create();
        $probe = new SmtpProbe;

        $blocked = $this->account($user, ['smtp_host' => '169.254.169.254']);
        $this->assertFalse($probe->probe($blocked)['ok']);

        $noHost = $this->account($user, ['smtp_host' => null]);
        $r = $probe->probe($noHost);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('SMTP host', $r['detail']);
    }

    public function test_account_present_exposes_smtp_never_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson(route('mail.accounts.store'), [
            'name' => 'Acct', 'host' => 'imap.example.com', 'port' => 993, 'username' => 'me@example.com',
            'password' => 'imap-pw', 'encryption' => 'ssl',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'me@example.com',
            'smtp_password' => 'smtp-pw', 'smtp_encryption' => 'starttls', 'from_email' => 'me@example.com', 'from_name' => 'Me',
        ])->assertCreated()
            ->assertJsonPath('account.smtp_host', 'smtp.example.com')
            ->assertJsonPath('account.from_email', 'me@example.com')
            ->assertJsonPath('account.has_smtp_password', true)
            ->assertJsonMissingPath('account.smtp_password')
            ->json('account.id');

        // Blank smtp_password on update keeps the stored one (KeepBlankSecrets).
        $this->putJson(route('mail.accounts.update', $id), [
            'name' => 'Acct2', 'host' => 'imap.example.com', 'port' => 993, 'username' => 'me@example.com',
            'encryption' => 'ssl', 'smtp_host' => 'smtp.example.com', 'from_email' => 'me@example.com', 'smtp_password' => '',
        ])->assertOk()->assertJsonPath('account.name', 'Acct2');
        $this->assertSame('smtp-pw', MailAccount::findOrFail($id)->smtp_password);
    }
}
