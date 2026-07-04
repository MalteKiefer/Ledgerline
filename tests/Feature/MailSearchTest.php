<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MailSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MailAccount $account;

    private MailFolder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        // Mail is per-user: the account (+ its folders/messages) belong to the
        // user who searches.
        $this->user = User::factory()->create();
        $this->account = MailAccount::create(['name' => 'W', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);
        $this->account->forceFill(['user_id' => $this->user->id])->save();
        $this->folder = MailFolder::create(['user_id' => $this->user->id, 'mail_account_id' => $this->account->id, 'path' => 'INBOX', 'name' => 'INBOX']);
    }

    private function message(array $attrs): MailMessage
    {
        return MailMessage::create(array_merge([
            'user_id' => $this->user->id,
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->folder->id,
            'uid' => random_int(1, 1_000_000),
            'uidvalidity' => 1,
            'blob' => Str::uuid()->toString(),
            'date_at' => now(),
            'synced_at' => now(),
        ], $attrs));
    }

    private function search(array $params): TestResponse
    {
        return $this->getJson(route('mail.archive.search', array_merge(['account' => $this->account->id], $params)));
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('mail.archive.search', $this->account->id))->assertRedirect(route('login'));
    }

    public function test_it_searches_subject_from_cc_body_and_attachment(): void
    {
        $this->actingAs($this->user);
        $target = $this->message([
            'subject' => 'Quarterly invoice',
            'from_name' => 'Acme', 'from_email' => 'billing@acme.test',
            'cc' => [['name' => 'Bob', 'email' => 'bob@partner.test']],
            'body_text' => 'Please find the pineapple report attached.',
            'has_attachments' => true, 'attachment_names' => ['report-2026.pdf'],
        ]);
        $this->message(['subject' => 'Lunch', 'from_email' => 'x@y.test', 'body_text' => 'nothing here']);

        // Each requested field matches the same target and nothing else.
        foreach (['invoice', 'billing@acme.test', 'bob@partner.test', 'pineapple', 'report-2026'] as $term) {
            $this->search(['q' => $term])
                ->assertOk()
                ->assertJsonPath('count', 1)
                ->assertJsonPath('messages.0.id', $target->id);
        }
    }

    public function test_it_filters_by_date_range_and_attachments(): void
    {
        $this->actingAs($this->user);
        $old = $this->message(['subject' => 'Old', 'date_at' => now()->subYear(), 'has_attachments' => false]);
        $recent = $this->message(['subject' => 'Recent', 'date_at' => now()->subDay(), 'has_attachments' => true, 'attachment_names' => ['a.pdf']]);

        $this->search(['date_from' => now()->subWeek()->toDateString()])
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('messages.0.id', $recent->id);

        $this->search(['has_attachment' => 1])
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('messages.0.id', $recent->id);

        $this->search(['date_to' => now()->subMonth()->toDateString()])
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('messages.0.id', $old->id);
    }

    public function test_it_searches_all_archived_mail_not_only_server_deleted(): void
    {
        $this->actingAs($this->user);
        // A message still on the server (not deleted) must be searchable, unlike
        // the recovery index which only lists server-deleted mail.
        $this->message(['subject' => 'Present', 'deleted_on_server_at' => null]);

        $this->search(['q' => 'Present'])->assertOk()->assertJsonPath('count', 1);
        $this->getJson(route('mail.archive', $this->account->id))->assertOk()->assertJsonPath('count', 0);
    }
}
