<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailBlob;
use App\Models\User;
use App\Support\UserData\MailData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function blob(User $user): string
    {
        $id = (string) Str::uuid();
        (new MailBlob)->forceFill(['blob' => $id, 'user_id' => $user->id, 'size' => 12, 'created_at' => now()])->save();
        Storage::disk(config('files.disk'))->put('mail/'.$id, 'raw eml bytes');

        return $id;
    }

    public function test_export_lists_the_blob_inventory(): void
    {
        $user = User::factory()->create();
        $this->blob($user);
        $this->blob($user);

        $export = (new MailData)->export($user);
        $this->assertCount(2, $export['blobs']);
    }

    public function test_purge_removes_bytes_and_ledger_only_for_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->blob($user);
        $theirs = $this->blob($other);

        (new MailData)->purge($user);

        Storage::disk(config('files.disk'))->assertMissing('mail/'.$mine);
        $this->assertDatabaseMissing('mail_blobs', ['blob' => $mine]);

        // Another user's bytes + ledger are untouched.
        Storage::disk(config('files.disk'))->assertExists('mail/'.$theirs);
        $this->assertDatabaseHas('mail_blobs', ['blob' => $theirs]);
    }

    public function test_mail_is_a_registered_gdpr_contributor(): void
    {
        $this->assertContains(MailData::class, config('user_data.contributors'));
    }
}
