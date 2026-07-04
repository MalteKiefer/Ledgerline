<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\MailSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordingMailSource implements MailSource
{
    public array $appended = [];

    public function folders(ImapCredentials $c): array
    {
        return [];
    }

    public function uids(ImapCredentials $c, string $folder): array
    {
        return [];
    }

    public function fetch(ImapCredentials $c, string $folder, int $uid): array
    {
        return [];
    }

    public function appendMessage(ImapCredentials $c, string $folder, string $raw): void
    {
        $this->appended[] = [$folder, $raw];
    }
}

class MailArchiveActionsTest extends TestCase
{
    use RefreshDatabase;

    private function archived(): MailMessage
    {
        $a = MailAccount::create(['name' => 'W', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);
        $f = MailFolder::create(['mail_account_id' => $a->id, 'path' => 'INBOX', 'name' => 'INBOX']);
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('mail/'.$blob, "Subject: Hi\r\n\r\nBody");

        return MailMessage::create([
            'mail_account_id' => $a->id, 'mail_folder_id' => $f->id, 'uid' => 5, 'uidvalidity' => 1,
            'subject' => 'Hi', 'blob' => $blob, 'deleted_on_server_at' => now(), 'synced_at' => now(),
        ]);
    }

    public function test_index_lists_archived_messages(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->signIn();
        $m = $this->archived();

        $this->getJson(route('mail.archive', $m->mail_account_id))
            ->assertOk()->assertJson(['count' => 1])->assertSee('Hi');
    }

    public function test_restore_appends_to_server_and_drops_local(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->signIn();
        $m = $this->archived();
        $source = new RecordingMailSource;
        $this->app->instance(MailSource::class, $source);

        $this->postJson(route('mail.archive.restore', $m))->assertOk()->assertJson(['ok' => true]);

        $this->assertCount(1, $source->appended);
        $this->assertSame('INBOX', $source->appended[0][0]);
        $this->assertSame(0, MailMessage::count());
        Storage::disk('files')->assertMissing('mail/'.$m->blob);
    }

    public function test_an_oversized_eml_is_refused_on_view(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files', 'mail_archive.max_render_bytes' => 1024]);
        $this->signIn();
        $m = $this->archived();
        Storage::disk('files')->put('mail/'.$m->blob, str_repeat('x', 2048));

        $this->getJson(route('mail.archive.show', $m))->assertStatus(413);
        $this->getJson(route('mail.archive.attachment', ['message' => $m, 'index' => 0]))->assertStatus(413);
    }

    public function test_permanent_delete_removes_row_and_blob(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->signIn();
        $m = $this->archived();

        $this->deleteJson(route('mail.archive.destroy', $m))->assertOk();

        $this->assertSame(0, MailMessage::count());
        Storage::disk('files')->assertMissing('mail/'.$m->blob);
    }

    public function test_cached_serves_an_archived_message_or_reports_not_found(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->signIn();
        $m = $this->archived();

        $this->postJson(route('mail.message.cached', $m->mail_account_id), ['folder' => 'INBOX', 'uid' => 5])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('message.archived', true)
            ->assertJsonPath('message.archiveId', $m->id);

        $this->postJson(route('mail.message.cached', $m->mail_account_id), ['folder' => 'INBOX', 'uid' => 999])
            ->assertOk()->assertJsonPath('found', false);
    }

    public function test_archive_one_captures_a_live_message_and_is_idempotent(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->signIn();
        $a = MailAccount::create(['name' => 'W', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);

        $this->app->instance(MailSource::class, new class extends RecordingMailSource
        {
            public function fetch(ImapCredentials $c, string $folder, int $uid): array
            {
                return ['raw' => "Subject: Live {$uid}\r\n\r\nBody", 'subject' => "Live {$uid}", 'to' => [], 'cc' => []];
            }
        });

        $this->postJson(route('mail.message.archive', $a->id), ['folder' => 'INBOX', 'uid' => 42, 'uidvalidity' => 7])
            ->assertOk()->assertJson(['archived' => true]);
        $this->assertDatabaseHas('mail_messages', ['mail_account_id' => $a->id, 'uid' => 42, 'uidvalidity' => 7]);

        // Already archived → no duplicate.
        $this->postJson(route('mail.message.archive', $a->id), ['folder' => 'INBOX', 'uid' => 42, 'uidvalidity' => 7])
            ->assertOk()->assertJson(['archived' => false]);
        $this->assertSame(1, MailMessage::where('uid', 42)->count());
    }
}
