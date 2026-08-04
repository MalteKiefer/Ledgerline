<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\Mail\IngestMailChunk;
use App\Models\MailAccount;
use App\Models\User;
use App\Services\Mail\IngestResult;
use App\Services\Mail\MaildirIngestor;
use App\Support\Mail\ImapDeleter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * The "delete after import" account flag: after a chunk archives messages, it
 * deletes exactly the freshly-stored origin UIDs from the origin mailbox in one
 * ImapDeleter session — and only when the flag is on.
 */
class IngestChunkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function tempFile(): string
    {
        $path = sys_get_temp_dir().'/ll-chunk-'.Str::uuid()->toString().',U=42:2,S';
        file_put_contents($path, 'x');

        return $path;
    }

    public function test_delete_after_import_expunges_the_stored_uid_from_origin(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'delete_after_import' => true, 'password' => 'pw']);
        $path = $this->tempFile();

        $ingestor = Mockery::mock(MaildirIngestor::class);
        $ingestor->shouldReceive('ingestFile')->once()->andReturn(IngestResult::stored('h', '42'));

        $this->mock(ImapDeleter::class, function ($m) use ($account): void {
            $m->shouldReceive('deleteUids')->once()
                ->withArgs(fn ($acc, $folder, $uids): bool => $acc->id === $account->id && $folder === 'INBOX' && $uids === ['42'])
                ->andReturn(1);
        });

        (new IngestMailChunk($account->id, 'INBOX', [$path]))->handle($ingestor);
        $this->addToAssertionCount(1); // Mockery verifies deleteUids([42]) at close.

        @unlink($path);
    }

    public function test_no_origin_delete_when_the_flag_is_off(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'delete_after_import' => false]);
        $path = $this->tempFile();

        $ingestor = Mockery::mock(MaildirIngestor::class);
        $ingestor->shouldReceive('ingestFile')->once()->andReturn(IngestResult::stored('h', '42'));

        $this->mock(ImapDeleter::class, fn ($m) => $m->shouldNotReceive('deleteUids'));

        (new IngestMailChunk($account->id, 'INBOX', [$path]))->handle($ingestor);
        $this->addToAssertionCount(1); // Mockery verifies deleteUids is never called.

        @unlink($path);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
