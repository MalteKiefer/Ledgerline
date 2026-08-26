<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailMessage;
use App\Models\User;
use App\Support\BlobStore;
use App\Support\Mail\MimeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A mail is archived at the moment it was sent, not at the sender's wall clock.
 *
 * Eloquent writes a datetime as the wall clock of the Carbon's own timezone, so
 * a Date header of "09:13:15 +0200" stored as parsed loses the offset and the
 * message appears two hours late — which puts a reply ahead of the mail it
 * answers.
 */
class MailDateOffsetTest extends TestCase
{
    use RefreshDatabase;

    private function eml(string $date): string
    {
        return implode("\r\n", [
            'From: Marlene <marlene@example.com>',
            'To: me@example.com',
            'Subject: Reminder',
            'Message-ID: <one@example.com>',
            'Date: '.$date,
            'Content-Type: text/plain; charset=utf-8',
            '',
            'body',
            '',
        ]);
    }

    public function test_an_offset_in_the_date_header_survives_being_stored(): void
    {
        $parsed = (new MimeParser)->parse($this->eml('Wed, 26 Aug 2026 09:13:15 +0200'));

        // Same instant, expressed in UTC — 07:13:15, not 09:13:15.
        $this->assertSame('2026-08-26T07:13:15+00:00', $parsed->date?->toIso8601String());

        $message = new MailMessage;
        $message->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'folder' => 'INBOX',
            'date' => $parsed->date,
            'content_hash' => hash('sha256', 'x'),
            'size' => 1,
            'created_at' => now(),
        ])->save();

        // What actually landed in the column, not what the model still holds.
        $this->assertSame('2026-08-26 07:13:15', (string) MailMessage::query()
            ->withoutGlobalScopes()
            ->whereKey($message->id)
            ->value('date'));
    }

    public function test_a_reply_stays_after_the_message_it_answers(): void
    {
        // The reported symptom: Marlene wrote at 09:13 +0200, the reply went out
        // at 09:44 UTC. Stored wrongly, hers becomes 09:13 UTC and sorts second.
        $hers = (new MimeParser)->parse($this->eml('Wed, 26 Aug 2026 09:13:15 +0200'))->date;
        $mine = (new MimeParser)->parse($this->eml('Wed, 26 Aug 2026 09:44:52 +0000'))->date;

        $this->assertTrue($hers?->lessThan($mine));
    }

    public function test_repair_rewrites_a_wrongly_stored_date_from_the_raw_message(): void
    {
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $id = (string) Str::uuid();

        BlobStore::disk()->put('mail/'.$id, $this->eml('Wed, 26 Aug 2026 09:13:15 +0200'));

        $message = new MailMessage;
        $message->forceFill([
            'id' => $id,
            'user_id' => $user->id,
            'folder' => 'INBOX',
            // The wrong value: the sender's wall clock kept as if it were UTC.
            'date' => '2026-08-26 09:13:15',
            'content_hash' => hash('sha256', 'y'),
            'size' => 1,
            'created_at' => now(),
        ])->save();

        $this->artisan('mail:repair-dates')->assertSuccessful();

        $this->assertSame('2026-08-26 07:13:15', (string) MailMessage::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->value('date'));
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        BlobStore::disk()->put('mail/'.$id, $this->eml('Wed, 26 Aug 2026 09:13:15 +0200'));

        (new MailMessage)->forceFill([
            'id' => $id, 'user_id' => $user->id, 'folder' => 'INBOX',
            'date' => '2026-08-26 09:13:15', 'content_hash' => hash('sha256', 'z'),
            'size' => 1, 'created_at' => now(),
        ])->save();

        $this->artisan('mail:repair-dates', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('2026-08-26 09:13:15', (string) MailMessage::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->value('date'));
    }

    public function test_a_message_whose_bytes_are_gone_is_left_alone(): void
    {
        // Nothing to re-read means nothing to correct; inventing a date would be
        // worse than leaving the one that is there.
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $id = (string) Str::uuid();

        (new MailMessage)->forceFill([
            'id' => $id, 'user_id' => $user->id, 'folder' => 'INBOX',
            'date' => '2026-08-26 09:13:15', 'content_hash' => hash('sha256', 'w'),
            'size' => 1, 'created_at' => now(),
        ])->save();

        $this->artisan('mail:repair-dates')->assertSuccessful();

        $this->assertSame('2026-08-26 09:13:15', (string) MailMessage::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->value('date'));
    }
}
