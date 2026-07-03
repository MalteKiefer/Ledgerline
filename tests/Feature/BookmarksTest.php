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

        $this->postJson(route('bookmarks.store'), ['title' => 'Laravel', 'url' => 'https://laravel.com', 'tags' => ['php', 'framework']])
            ->assertCreated()->assertJson(['title' => 'Laravel', 'tags' => ['php', 'framework']]);

        $this->assertSame(1, Bookmark::count());
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
