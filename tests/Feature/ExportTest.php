<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    // The Downloads centre keeps the async export/download infrastructure
    // (listing, serving, pruning). No module currently produces exports: every
    // module is end-to-end encrypted, so the server cannot build a readable
    // archive. These tests exercise the surviving download-management surface.

    public function test_downloads_page_lists_and_serves_and_deletes(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'files', 'variant' => 'original',
            'title' => 'x', 'status' => 'ready', 'item_count' => 1, 'part_count' => 1,
            'total_size' => 3, 'files' => [['name' => 'x.zip', 'path' => 'exports/1/1/part-1.zip', 'size' => 3]],
            'expires_at' => now()->addDays(7),
        ]);
        Storage::disk('files')->put('exports/1/1/part-1.zip', 'ZIP');

        $this->getJson(route('downloads.data'))
            ->assertOk()
            ->assertJsonPath('exports.0.id', $export->id)
            ->assertJsonPath('exports.0.status', 'ready');

        $this->get(route('downloads.part', ['export' => $export->id, 'index' => 0]))->assertOk();

        $this->deleteJson(route('downloads.destroy'), ['ids' => [$export->id]])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('files')->assertMissing('exports/1/1/part-1.zip');
    }

    public function test_a_user_cannot_download_another_users_export(): void
    {
        Storage::fake('files');
        $this->signIn();
        $other = Export::create([
            'user_id' => 99999, 'source' => 'files', 'title' => 'x', 'status' => 'ready',
            'files' => [['name' => 'x.zip', 'path' => 'exports/9/9/part-1.zip', 'size' => 1]],
            'expires_at' => now()->addDay(),
        ]);

        // Another user's export is invisible (global owner scope → 404), not
        // merely forbidden.
        $this->get(route('downloads.part', ['export' => $other->id, 'index' => 0]))->assertNotFound();
    }

    public function test_unseen_ready_exports_badge_and_clearing(): void
    {
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'x', 'status' => 'ready',
            'files' => [], 'expires_at' => now()->addDay(),
        ]);
        Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'building', 'status' => 'processing',
        ]);
        Export::create([
            'user_id' => 99999, 'source' => 'files', 'title' => 'foreign', 'status' => 'ready',
            'expires_at' => now()->addDay(),
        ]);

        // Only the own, ready, unseen export counts.
        $this->assertSame(1, Export::unseenReadyCount($user->id));

        // Visiting the Downloads page marks it seen and clears the badge.
        $this->get(route('downloads.index'))->assertOk();
        $this->assertNotNull($export->fresh()->seen_at);
        $this->assertSame(0, Export::unseenReadyCount($user->id));

        // The foreign user's badge is untouched.
        $this->assertSame(1, Export::unseenReadyCount(99999));
    }

    public function test_nav_shows_downloads_badge_for_unseen_ready_export(): void
    {
        $user = $this->signIn();
        Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'x', 'status' => 'ready',
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee(__('downloads.new_ready'));
    }

    public function test_prune_removes_expired_exports_and_their_files(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'old', 'status' => 'ready',
            'files' => [['name' => 'x.zip', 'path' => 'exports/1/1/part-1.zip', 'size' => 3]],
            'expires_at' => now()->subDay(),
        ]);
        Storage::disk('files')->put('exports/1/1/part-1.zip', 'ZIP');

        $this->artisan('exports:prune')->assertOk();

        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('files')->assertMissing('exports/1/1/part-1.zip');
    }
}
