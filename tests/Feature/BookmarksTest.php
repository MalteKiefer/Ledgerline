<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookmarksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('bookmarks.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_and_data_load(): void
    {
        $this->signIn();
        $this->get(route('bookmarks.index'))->assertOk();
        $this->getJson(route('bookmarks.data'))->assertOk()->assertJson(['folders' => [], 'bookmarks' => []]);
    }

    public function test_it_creates_a_bookmark_with_tags(): void
    {
        $this->signIn();

        $this->postJson(route('bookmarks.store'), ['title' => 'Laravel', 'url' => 'https://laravel.com', 'tags' => ['php', 'framework']])
            ->assertCreated()->assertJson(['title' => 'Laravel', 'tags' => ['php', 'framework']]);

        $this->assertSame(1, Bookmark::count());
    }

    public function test_read_later_queue_and_mark_read(): void
    {
        $this->signIn();
        $this->postJson(route('bookmarks.store'), ['title' => 'Article', 'url' => 'https://example.com/a'])->assertCreated();
        $bookmark = Bookmark::firstOrFail();

        // Queue it, then mark it read.
        $this->patchJson(route('bookmarks.patch', $bookmark), ['read_later' => true])
            ->assertOk()->assertJson(['readLater' => true, 'read' => false]);
        $this->patchJson(route('bookmarks.patch', $bookmark), ['read' => true])
            ->assertOk()->assertJson(['read' => true]);

        $bookmark->refresh();
        $this->assertTrue($bookmark->read_later);
        $this->assertNotNull($bookmark->read_at);

        // Re-queueing clears the read stamp.
        $this->patchJson(route('bookmarks.patch', $bookmark), ['read_later' => true])
            ->assertOk()->assertJson(['read' => false]);
        $this->assertNull($bookmark->fresh()->read_at);
    }

    public function test_export_produces_netscape_html(): void
    {
        $this->signIn();
        $folder = BookmarkFolder::create(['name' => 'Dev']);
        Bookmark::create(['bookmark_folder_id' => $folder->id, 'title' => 'Laravel', 'url' => 'https://laravel.com', 'tags' => ['php']]);
        Bookmark::create(['title' => 'Loose', 'url' => 'https://example.com']);

        $body = $this->get(route('bookmarks.export'))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->streamedContent();

        $this->assertStringContainsString('NETSCAPE-Bookmark-file-1', $body);
        $this->assertStringContainsString('<H3 ADD_DATE', $body);
        $this->assertStringContainsString('HREF="https://laravel.com"', $body);
        $this->assertStringContainsString('TAGS="php"', $body);
        $this->assertStringContainsString('HREF="https://example.com"', $body);
    }

    public function test_import_creates_folders_and_skips_duplicates(): void
    {
        $this->signIn();
        Bookmark::create(['title' => 'Existing', 'url' => 'https://laravel.com']);

        $html = <<<'HTML'
        <!DOCTYPE NETSCAPE-Bookmark-file-1>
        <DL><p>
            <DT><H3>Dev</H3>
            <DL><p>
                <DT><A HREF="https://laravel.com" TAGS="php">Laravel</A>
                <DT><A HREF="https://symfony.com" TAGS="php,framework">Symfony</A>
                <DD>A PHP framework
            </DL><p>
            <DT><A HREF="javascript:alert(1)">Bad</A>
            <DT><A HREF="https://example.org">Loose</A>
        </DL><p>
        HTML;
        $file = UploadedFile::fake()->createWithContent('bookmarks.html', $html);

        $this->post(route('bookmarks.import'), ['file' => $file])
            ->assertOk()->assertJson(['created' => 2, 'skipped' => 1]);

        // laravel.com skipped (dup), javascript: dropped, symfony + example.org created.
        $this->assertNull(Bookmark::where('url', 'javascript:alert(1)')->first());
        $symfony = Bookmark::where('url', 'https://symfony.com')->firstOrFail();
        $this->assertSame('A PHP framework', $symfony->description);
        $this->assertSame(['php', 'framework'], $symfony->tags);
        $this->assertNotNull($symfony->folder);
        $this->assertSame('Dev', $symfony->folder->name);
        $this->assertNull(Bookmark::where('url', 'https://example.org')->firstOrFail()->bookmark_folder_id);
    }

    public function test_import_is_owner_scoped_for_duplicate_detection(): void
    {
        // A URL owned by another user must not count as an existing duplicate.
        $other = User::factory()->create();
        Bookmark::withoutGlobalScopes()->create(['user_id' => $other->id, 'title' => 'Theirs', 'url' => 'https://shared.example']);

        $this->signIn();
        $html = '<DL><p><DT><A HREF="https://shared.example">Mine</A></DL><p>';
        $file = UploadedFile::fake()->createWithContent('b.html', $html);

        $this->post(route('bookmarks.import'), ['file' => $file])->assertOk()->assertJson(['created' => 1]);
        $this->assertSame(1, Bookmark::count()); // own scope: just mine
    }

    public function test_link_checker_flags_dead_and_clears_live(): void
    {
        $user = $this->signIn();
        Http::fake([
            'dead.example/*' => Http::response('', 404),
            'live.example/*' => Http::response('ok', 200),
        ]);

        $dead = Bookmark::create(['title' => 'Dead', 'url' => 'https://dead.example/x']);
        $live = Bookmark::create(['title' => 'Live', 'url' => 'https://live.example/y']);

        $this->artisan('bookmarks:check-links')->assertSuccessful();

        $this->assertNotNull($dead->fresh()->dead_at);
        $this->assertNotNull($dead->fresh()->last_checked_at);
        $this->assertNull($live->fresh()->dead_at);
    }

    public function test_a_javascript_url_is_rejected(): void
    {
        $this->signIn();

        $this->post(route('bookmarks.store'), ['title' => 'x', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->post(route('bookmarks.store'), ['title' => 'x', 'url' => 'data:text/html,<script>alert(1)</script>'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, Bookmark::count());
    }

    public function test_patch_favorite_and_trash(): void
    {
        $this->signIn();
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test']);

        $this->patchJson(route('bookmarks.patch', $b), ['favorite' => true])->assertOk()->assertJson(['favorite' => true]);
        $this->patchJson(route('bookmarks.patch', $b), ['trashed' => true])->assertOk()->assertJson(['trashed' => true]);
    }

    public function test_deleting_a_folder_keeps_its_bookmarks(): void
    {
        $this->signIn();
        $folder = BookmarkFolder::create(['name' => 'Dev']);
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test', 'bookmark_folder_id' => $folder->id]);

        $this->deleteJson(route('bookmarks.folders.destroy', $folder))->assertOk();

        $this->assertNull($b->refresh()->bookmark_folder_id);
        $this->assertSame(1, Bookmark::count());
    }

    public function test_bookmarks_appear_in_global_search(): void
    {
        $this->signIn();
        Bookmark::create(['title' => 'Uniquemark', 'url' => 'https://searchable.test']);

        $this->getJson(route('search.suggest', ['q' => 'searchable']))->assertOk()->assertSee('Uniquemark');
    }
}
