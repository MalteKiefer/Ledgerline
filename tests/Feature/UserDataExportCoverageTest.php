<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\PurgeUserAccount;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FolderShare;
use App\Models\FolderShareMember;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Models\MailRule;
use App\Models\MailSavedSearch;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\UserData\CalendarData;
use App\Support\UserData\FilesData;
use App\Support\UserData\FinanceData;
use App\Support\UserData\MailData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserDataExportCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_covers_calendar_mail_shares_and_finance(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Calendar + event.
        $calendar = (new Calendar)->forceFill([
            'user_id' => $user->id, 'name' => 'Personal', 'uri' => 'personal',
        ]);
        $calendar->save();
        (new CalendarEvent)->forceFill([
            'calendar_id' => $calendar->id, 'uri' => 'e1.ics', 'etag' => 'abc',
            'ics' => "BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Lunch\nEND:VEVENT\nEND:VCALENDAR",
            'summary' => 'Lunch',
        ])->save();

        // Mail metadata + labels + rules + saved searches.
        (new MailMessage)->forceFill([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'folder' => 'INBOX',
            'content_hash' => 'hash-1', 'size' => 42, 'subject' => 'Hello', 'from_email' => 'a@example.com',
        ])->save();
        (new MailLabel)->forceFill(['user_id' => $user->id, 'name' => 'Work'])->save();
        (new MailRule)->forceFill([
            'user_id' => $user->id, 'name' => 'Rule A', 'match_json' => ['from' => 'x'], 'action_json' => ['trash' => true],
        ])->save();
        (new MailSavedSearch)->forceFill([
            'user_id' => $user->id, 'name' => 'Search A', 'filters_json' => ['q' => 'invoice'],
        ])->save();

        // Files: public share + cross-user folder share + member.
        $folder = (new FileFolder)->forceFill(['user_id' => $user->id, 'name' => 'Docs']);
        $folder->save();
        $file = (new FileEntry)->forceFill([
            'user_id' => $user->id, 'file_folder_id' => $folder->id, 'name' => 'a.txt',
            'mime' => 'text/plain', 'size' => 3, 'storage_path' => 'files/'.Str::uuid(), 'sha256' => str_repeat('0', 64),
        ]);
        $file->save();
        (new FileShare)->forceFill([
            'user_id' => $user->id, 'token' => 'share-token-1', 'kind' => 'file', 'file_id' => $file->id,
        ])->save();
        $folderShare = (new FolderShare)->forceFill([
            'owner_id' => $user->id, 'file_folder_id' => $folder->id,
        ]);
        $folderShare->save();
        (new FolderShareMember)->forceFill([
            'folder_share_id' => $folderShare->id, 'user_id' => $other->id, 'role' => 'viewer',
        ])->save();

        // Finance: payment method + partner + project.
        (new PaymentMethod)->forceFill([
            'user_id' => $user->id, 'type' => 'card', 'name' => 'Visa', 'card_number' => '4111111111111111',
        ])->save();
        (new FinancePartner)->forceFill(['user_id' => $user->id, 'name' => 'ACME'])->save();
        (new FinanceProject)->forceFill(['user_id' => $user->id, 'name' => 'House'])->save();

        $calExport = (new CalendarData)->export($user);
        $this->assertCount(1, $calExport['calendars']);
        $this->assertCount(1, $calExport['events']);
        $this->assertSame('Lunch', $calExport['events'][0]['summary']);

        $mailExport = (new MailData)->export($user);
        $this->assertNotEmpty($mailExport['messages']);
        $this->assertSame('Hello', $mailExport['messages'][0]['subject']);
        $this->assertNotEmpty($mailExport['labels']);
        $this->assertNotEmpty($mailExport['rules']);
        $this->assertNotEmpty($mailExport['saved_searches']);

        $filesExport = (new FilesData)->export($user);
        $this->assertNotEmpty($filesExport['file_shares']);
        $this->assertNotEmpty($filesExport['folder_shares']);
        $this->assertNotEmpty($filesExport['folder_share_members']);
        // password_hash is a secret ($hidden) and must never appear in the export.
        $this->assertArrayNotHasKey('password_hash', $filesExport['file_shares'][0]);

        $financeExport = (new FinanceData)->export($user);
        $this->assertNotEmpty($financeExport['payment_methods']);
        $this->assertNotEmpty($financeExport['partners']);
        $this->assertNotEmpty($financeExport['projects']);
        // Raw card PAN must never be exported.
        $this->assertArrayNotHasKey('card_number', $financeExport['payment_methods'][0]);
    }

    public function test_purge_revokes_tokens_and_erases_calendar(): void
    {
        $user = User::factory()->create();
        $keep = User::factory()->create();

        $user->createToken('device');
        $keep->createToken('device');

        (new Calendar)->forceFill(['user_id' => $user->id, 'name' => 'Personal', 'uri' => 'personal'])->save();

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        app(PurgeUserAccount::class)->handle($user);

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->assertSame(0, Calendar::withoutGlobalScopes()->where('user_id', $user->id)->count());

        // Other user's token and account survive.
        $this->assertNotNull(User::find($keep->id));
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $keep->id)->count());
    }
}
