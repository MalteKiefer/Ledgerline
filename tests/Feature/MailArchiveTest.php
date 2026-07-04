<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\MailArchiver;
use App\Services\Mail\MailSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** In-memory fake IMAP source: folders + per-folder uid=>flags, raw+meta on fetch. */
class FakeMailSource implements MailSource
{
    /**
     * @param  array<string,array<int,array>>  $data  folder => [uid => flags]
     * @param  list<string>  $throwOn  folders whose uids() throws (simulate a quirky server folder)
     */
    public function __construct(public array $data, public int $validity = 1, public array $throwOn = []) {}

    public function folders(ImapCredentials $c): array
    {
        return array_map(fn ($path) => [
            'path' => $path, 'name' => $path, 'delimiter' => '/', 'role' => null, 'uidvalidity' => $this->validity,
        ], array_keys($this->data));
    }

    public function uids(ImapCredentials $c, string $folder): array
    {
        if (in_array($folder, $this->throwOn, true)) {
            throw new \RuntimeException('Command failed to process: Empty response');
        }

        return $this->data[$folder] ?? [];
    }

    public function fetch(ImapCredentials $c, string $folder, int $uid): array
    {
        return [
            'raw' => "From: a@x.test\r\nSubject: Msg {$uid}\r\n\r\nBody {$uid}",
            'message_id' => "<{$uid}@x.test>", 'subject' => "Msg {$uid}",
            'from_name' => 'A', 'from_email' => 'a@x.test', 'to' => [], 'date' => '2026-01-01 10:00:00',
            'has_attachments' => false, 'size' => 100, 'preview' => "Body {$uid}",
        ];
    }

    public array $appended = [];

    public function appendMessage(ImapCredentials $c, string $folder, string $raw): void
    {
        $this->appended[] = [$folder, $raw];
    }
}

class MailArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function account(): MailAccount
    {
        return MailAccount::create(['name' => 'W', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);
    }

    public function test_it_fetches_and_stores_new_messages(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();
        $source = new FakeMailSource(['INBOX' => [1 => ['seen' => true], 2 => ['seen' => false]]]);

        $r = (new MailArchiver($source))->syncAccount($a);

        $this->assertSame(2, $r['new']);
        $this->assertSame(2, MailMessage::count());
        $m = MailMessage::where('uid', 1)->first();
        $this->assertTrue($m->seen);
        Storage::disk('files')->assertExists('mail/'.$m->blob);
    }

    public function test_a_failing_folder_is_skipped_and_the_rest_still_sync(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();
        // "Bad" answers with an empty response (iCloud quirk); it must not abort
        // the whole account — INBOX still archives.
        $source = new FakeMailSource(
            ['INBOX' => [1 => ['seen' => true]], 'Bad' => [2 => ['seen' => false]]],
            validity: 1,
            throwOn: ['Bad'],
        );

        $r = (new MailArchiver($source))->syncAccount($a);

        $this->assertSame(1, $r['new']);
        $this->assertSame(2, $r['folders']);
        $this->assertSame(1, MailMessage::count());
        $this->assertNotNull($a->fresh()->last_synced_at);
    }

    public function test_server_deleted_mail_is_kept_and_archived(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();

        // First run: two messages.
        (new MailArchiver(new FakeMailSource(['INBOX' => [1 => [], 2 => []]])))->syncAccount($a);
        // Second run: uid 2 gone from the server.
        $r = (new MailArchiver(new FakeMailSource(['INBOX' => [1 => []]])))->syncAccount($a);

        $this->assertSame(1, $r['archived']);
        $this->assertSame(2, MailMessage::count()); // nothing lost
        $this->assertNotNull(MailMessage::where('uid', 2)->first()->deleted_on_server_at);
        $this->assertNull(MailMessage::where('uid', 1)->first()->deleted_on_server_at);
    }

    public function test_flags_update_on_resync(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();

        (new MailArchiver(new FakeMailSource(['INBOX' => [1 => ['seen' => false]]])))->syncAccount($a);
        (new MailArchiver(new FakeMailSource(['INBOX' => [1 => ['seen' => true, 'flagged' => true]]])))->syncAccount($a);

        $m = MailMessage::where('uid', 1)->first();
        $this->assertTrue($m->seen);
        $this->assertTrue($m->flagged);
        $this->assertSame(1, MailMessage::count()); // not duplicated
    }

    public function test_the_per_run_cap_bounds_fetches_newest_first(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();
        $source = new FakeMailSource(['INBOX' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []]]);

        (new MailArchiver($source))->syncAccount($a, perRunCap: 2);

        $this->assertSame(2, MailMessage::count());
        // Newest (highest uid) first.
        $this->assertEqualsCanonicalizing([5, 4], MailMessage::pluck('uid')->all());
    }

    public function test_the_cap_is_per_folder_so_every_folder_makes_progress(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();
        // Two folders; a per-folder cap of 1 must fetch the newest from EACH,
        // not spend the whole budget on the first folder.
        $source = new FakeMailSource([
            'INBOX' => [1 => [], 2 => [], 3 => []],
            'Archive' => [10 => [], 11 => []],
        ]);

        (new MailArchiver($source))->syncAccount($a, perRunCap: 1);

        $this->assertSame(2, MailMessage::count());
        $this->assertSame(2, MailMessage::distinct('mail_folder_id')->count('mail_folder_id'));
        // Newest per folder.
        $this->assertEqualsCanonicalizing([3, 11], MailMessage::pluck('uid')->all());
    }

    public function test_a_uidvalidity_change_archives_old_and_refills(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $a = $this->account();

        (new MailArchiver(new FakeMailSource(['INBOX' => [1 => [], 2 => []]], validity: 1)))->syncAccount($a);
        (new MailArchiver(new FakeMailSource(['INBOX' => [1 => []]], validity: 2)))->syncAccount($a);

        // Old validity-1 messages archived; new validity-2 uid 1 stored fresh.
        $this->assertSame(2, MailMessage::whereNotNull('deleted_on_server_at')->count());
        $this->assertSame(1, MailMessage::whereNull('deleted_on_server_at')->where('uidvalidity', 2)->count());
    }

    public function test_the_command_runs(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->account();
        $this->app->instance(MailSource::class, new FakeMailSource(['INBOX' => [1 => []]]));

        $this->artisan('mail:sync')->assertSuccessful();
        $this->assertSame(1, MailMessage::count());
    }
}
