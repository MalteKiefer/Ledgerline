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

    public function test_the_page_loads_without_a_vault(): void
    {
        $this->signIn();
        $this->get(route('bookmarks.index'))->assertOk();
    }

    public function test_it_creates_a_bookmark_with_tags(): void
    {
        $this->signIn();

        $this->post(route('bookmarks.store'), [
            'title' => 'Laravel', 'url' => 'https://laravel.com', 'tags' => 'php, framework',
        ])->assertRedirect(route('bookmarks.index'));

        $b = Bookmark::firstWhere('url', 'https://laravel.com');
        $this->assertSame(['php', 'framework'], $b->tags);
    }

    public function test_favorite_toggle_and_trash(): void
    {
        $this->signIn();
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test']);

        $this->post(route('bookmarks.favorite', $b))->assertRedirect();
        $this->assertTrue($b->refresh()->favorite);

        $this->post(route('bookmarks.trash', $b))->assertRedirect();
        $this->assertNotNull($b->refresh()->trashed_at);
    }

    public function test_deleting_a_folder_keeps_its_bookmarks(): void
    {
        $this->signIn();
        $folder = BookmarkFolder::create(['name' => 'Dev']);
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test', 'bookmark_folder_id' => $folder->id]);

        $this->delete(route('bookmarks.folders.destroy', $folder))->assertRedirect(route('bookmarks.index'));

        $this->assertNull($b->refresh()->bookmark_folder_id);
        $this->assertSame(1, Bookmark::count());
    }

    public function test_bookmarks_appear_in_global_search(): void
    {
        $this->signIn();
        Bookmark::create(['title' => 'Uniquemark', 'url' => 'https://searchable.test']);

        $this->getJson(route('search.suggest', ['q' => 'searchable']))
            ->assertOk()->assertSee('Uniquemark');
    }
}
