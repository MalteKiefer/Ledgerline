<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\FilesChangesController;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\User;
use App\Support\FileChangeSignal;
use App\Support\SseSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Observers\FileChangeObserver + App\Support\FileChangeSignal (the
 * signal a sync client's SSE connection polls for — see
 * FilesChangesController) and the controller's own auth guard + streamed
 * output shape, plus App\Support\SseSlot's per-user concurrency cap. The
 * controller's real poll loop runs for DEFAULT_MAX_SECONDS/
 * DEFAULT_POLL_SECONDS (45s/2s) in production; tests construct it directly
 * with tiny values instead of hitting the route, so exercising the actual
 * loop doesn't mean a 45-second test.
 */
class FilesChangesStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // FileEntry::forceCreate below fires the *existing* FileEntryObserver
        // too (search-text indexing, QUEUE_CONNECTION=sync so it runs
        // inline) — fake the disk so it has a real (empty) file to not-find
        // instead of reaching for a storage_path that was never written.
        Storage::fake(config('files.disk'));
    }

    public function test_stream_requires_authentication(): void
    {
        $this->getJson(route('api.files.changes-stream'))->assertUnauthorized();
    }

    public function test_file_entry_create_update_delete_restore_each_touch_the_signal(): void
    {
        $user = User::factory()->create();
        $this->assertNull(FileChangeSignal::lastChangedAt($user->id));

        $file = FileEntry::forceCreate([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'storage_path' => 'irrelevant/a.txt',
            'size' => 1,
        ]);
        $afterCreate = FileChangeSignal::lastChangedAt($user->id);
        $this->assertNotNull($afterCreate);

        usleep(1_000);
        $file->forceFill(['name' => 'b.txt'])->save();
        $afterUpdate = FileChangeSignal::lastChangedAt($user->id);
        $this->assertGreaterThan($afterCreate, $afterUpdate);

        usleep(1_000);
        $file->delete(); // soft delete
        $afterDelete = FileChangeSignal::lastChangedAt($user->id);
        $this->assertGreaterThan($afterUpdate, $afterDelete);

        usleep(1_000);
        $file->restore();
        $afterRestore = FileChangeSignal::lastChangedAt($user->id);
        $this->assertGreaterThan($afterDelete, $afterRestore);
    }

    public function test_file_folder_changes_also_touch_the_signal(): void
    {
        $user = User::factory()->create();

        $folder = FileFolder::forceCreate(['user_id' => $user->id, 'name' => 'Docs']);
        $this->assertNotNull(FileChangeSignal::lastChangedAt($user->id));
    }

    public function test_two_different_users_have_independent_signals(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        FileEntry::forceCreate(['user_id' => $a->id, 'name' => 'a.txt', 'storage_path' => 'x/a.txt', 'size' => 1]);

        $this->assertNotNull(FileChangeSignal::lastChangedAt($a->id));
        $this->assertNull(FileChangeSignal::lastChangedAt($b->id));
    }

    public function test_stream_response_has_sse_headers(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/files/changes-stream', 'GET');
        $request->setUserResolver(fn () => $user);

        // maxSeconds=0: the loop body never runs (only relevant for
        // nextEvent, tested directly below) — this just checks the
        // response's own shape, not the streamed content.
        $response = (new FilesChangesController(maxSeconds: 0, pollSeconds: 0))->stream($request);

        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        // Symfony's Response::prepare() appends "private" to whatever
        // Cache-Control we set — assert the directive we actually care
        // about (SSE must never be cached) rather than the exact string.
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    // -- App\Support\SseSlot: the per-user concurrency cap that bounds how
    // many Octane workers (a small, fixed pool — see SseSlot's doc comment)
    // this stream can tie up at once. --

    public function test_sse_slot_acquire_is_capped_and_release_frees_it_again(): void
    {
        // A real, freshly-created user id (not a hardcoded literal): with
        // RefreshDatabase truncating between tests, this is guaranteed not
        // to collide with a slot another test in the same PHPUnit process
        // may have left in the array cache store.
        $userId = User::factory()->create()->id;
        $held = [];
        for ($i = 0; $i < SseSlot::CAP; $i++) {
            $slot = SseSlot::acquire($userId, 60);
            $this->assertNotNull($slot, "slot #$i should have been free");
            $held[] = $slot;
        }
        $this->assertSame(range(0, SseSlot::CAP - 1), $held, 'slots are claimed in order 0..CAP-1');

        // Every slot is now held — one more claim must be refused, not just
        // "eventually" reused, since that's exactly what would let a
        // connection past the cap.
        $this->assertNull(SseSlot::acquire($userId, 60));

        SseSlot::release($userId, $held[0]);
        $this->assertSame(0, SseSlot::acquire($userId, 60), 'the freed slot is available again');
    }

    public function test_sse_slot_cap_is_per_user(): void
    {
        $a = User::factory()->create()->id;
        $b = User::factory()->create()->id;

        for ($i = 0; $i < SseSlot::CAP; $i++) {
            $this->assertNotNull(SseSlot::acquire($a, 60));
        }
        $this->assertNull(SseSlot::acquire($a, 60), 'user a is now at the cap');
        // A different user's cap must be entirely unaffected by user a's.
        $this->assertNotNull(SseSlot::acquire($b, 60));
    }

    public function test_stream_returns_503_once_the_per_user_concurrency_cap_is_reached(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < SseSlot::CAP; $i++) {
            $this->assertNotNull(SseSlot::acquire($user->id, 60));
        }

        $request = Request::create('/api/v1/files/changes-stream', 'GET');
        $request->setUserResolver(fn () => $user);
        $response = (new FilesChangesController(maxSeconds: 0, pollSeconds: 0))->stream($request);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['error' => 'too_many_streams'], json_decode((string) $response->getContent(), true));
    }

    public function test_stream_releases_its_slot_after_the_loop_ends(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/files/changes-stream', 'GET');
        $request->setUserResolver(fn () => $user);

        // maxSeconds=0 (loop body never runs, same as test_stream_response_
        // has_sse_headers above) exercises the acquire-then-release path
        // without actually running the poll loop: even though nothing ran,
        // the finally block must still have freed the slot it claimed —
        // otherwise every stream, however short, would permanently eat into
        // the cap.
        $response = (new FilesChangesController(maxSeconds: 0, pollSeconds: 0))->stream($request);
        $this->assertNotSame(503, $response->getStatusCode());

        // Drain the StreamedResponse's callback the same way the real HTTP
        // kernel would (sendContent()), so the try/finally inside actually
        // executes instead of just sitting there unevaluated. Not wrapped in
        // an extra ob_start() of our own: the callback's own leading
        // `while (ob_get_level() > 0) ob_end_flush()` would immediately pop
        // and flush any buffer we started here too — it can't tell "the
        // framework's" buffers apart from a test's — so it prints a couple
        // of bytes of real SSE text to stdout; harmless test noise, not a
        // buffering bug.
        $response->sendContent();

        for ($i = 0; $i < SseSlot::CAP; $i++) {
            $this->assertNotNull(SseSlot::acquire($user->id, 60), 'the slot the stream held should have been released');
        }
    }

    public function test_next_event_reports_a_change_once_then_falls_back_to_pings(): void
    {
        $user = User::factory()->create();
        FileEntry::forceCreate(['user_id' => $user->id, 'name' => 'a.txt', 'storage_path' => 'x/a.txt', 'size' => 1]);
        $changedAt = FileChangeSignal::lastChangedAt($user->id);

        [$line, $lastSeen] = FilesChangesController::nextEvent($user->id, null);
        $this->assertSame('data: '.json_encode(['changedAt' => $changedAt])."\n\n", $line);
        $this->assertSame($changedAt, $lastSeen);

        // Same signal value again (nothing new happened) -> falls back to a
        // ping, and $lastSeen is carried forward unchanged.
        [$line, $lastSeen] = FilesChangesController::nextEvent($user->id, $lastSeen);
        $this->assertSame(": ping\n\n", $line);
        $this->assertSame($changedAt, $lastSeen);
    }

    public function test_next_event_pings_when_nothing_has_ever_changed(): void
    {
        $user = User::factory()->create();
        [$line, $lastSeen] = FilesChangesController::nextEvent($user->id, null);
        $this->assertSame(": ping\n\n", $line);
        $this->assertNull($lastSeen);
    }
}
