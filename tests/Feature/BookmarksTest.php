<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->postJson(route('bookmarks.store'), ['enc_bookmark' => 'sealed', 'favorite' => true])
            ->assertCreated()->assertJson(['enc_bookmark' => 'sealed', 'favorite' => true]);

        $this->assertSame(1, Bookmark::count());
        $bookmark = Bookmark::firstOrFail();
        $this->assertSame('sealed', $bookmark->enc_bookmark);
        $this->assertTrue($bookmark->is_encrypted);
        $this->assertNull($bookmark->title);
        $this->assertNull($bookmark->url);
    }

    public function test_read_later_queue_and_mark_read(): void
    {
        $this->signIn();
        $this->postJson(route('bookmarks.store'), ['enc_bookmark' => 'sealed'])->assertCreated();
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

    public function test_patch_favorite_and_trash(): void
    {
        $this->signIn();
        $b = Bookmark::create(['enc_bookmark' => 'sealed', 'is_encrypted' => true]);

        $this->patchJson(route('bookmarks.patch', $b), ['favorite' => true])->assertOk()->assertJson(['favorite' => true]);
        $this->patchJson(route('bookmarks.patch', $b), ['trashed' => true])->assertOk()->assertJson(['trashed' => true]);
    }

    public function test_deleting_a_folder_keeps_its_bookmarks(): void
    {
        $this->signIn();
        $folder = BookmarkFolder::create(['name' => 'Dev']);
        $b = Bookmark::create(['enc_bookmark' => 'sealed', 'is_encrypted' => true, 'bookmark_folder_id' => $folder->id]);

        $this->deleteJson(route('bookmarks.folders.destroy', $folder))->assertOk();

        $this->assertNull($b->refresh()->bookmark_folder_id);
        $this->assertSame(1, Bookmark::count());
    }
}
