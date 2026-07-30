<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarksRelationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookmark_crud_and_toggle(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('bookmarks.store'), ['url' => 'https://example.com', 'tags' => ['news']])
            ->assertCreated()->assertJsonPath('bookmark.title', 'example.com')->json('bookmark.id');

        $this->getJson(route('bookmarks.list'))->assertOk()->assertJsonCount(1, 'bookmarks');

        $this->postJson(route('bookmarks.toggle', $id), ['field' => 'favorite', 'value' => true])
            ->assertOk()->assertJsonPath('bookmark.favorite', true);
        $this->putJson(route('bookmarks.update', $id), ['url' => 'https://example.org', 'title' => 'Org', 'version' => 0])
            ->assertOk()->assertJsonPath('bookmark.title', 'Org');
        $this->putJson(route('bookmarks.update', $id), ['url' => 'https://x.test', 'version' => 0])->assertStatus(409);
    }

    public function test_folders_nested_and_fk_null_on_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $parent = $this->postJson(route('bookmarks.folders.store'), ['name' => 'Dev'])->assertCreated()->json('folder.id');
        $child = $this->postJson(route('bookmarks.folders.store'), ['name' => 'Laravel', 'parent_id' => $parent])->assertCreated()->json('folder.id');
        $bId = $this->postJson(route('bookmarks.store'), ['url' => 'https://laravel.com', 'bookmark_folder_id' => $child])->assertCreated()->json('bookmark.id');

        // Delete parent: child reparents to root (self-FK nullOnDelete); the
        // bookmark lives in the child, so it is untouched.
        $this->deleteJson(route('bookmarks.folders.destroy', $parent))->assertOk();
        $this->assertNull(BookmarkFolder::find($child)->parent_id);
        $this->assertSame($child, Bookmark::find($bId)->bookmark_folder_id);

        // Delete the child: its bookmark becomes unfiled (folder-FK nullOnDelete).
        $this->deleteJson(route('bookmarks.folders.destroy', $child))->assertOk();
        $this->assertNull(Bookmark::find($bId)->bookmark_folder_id);
    }

    public function test_folder_cycle_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->postJson(route('bookmarks.folders.store'), ['name' => 'A'])->json('folder.id');
        $b = $this->postJson(route('bookmarks.folders.store'), ['name' => 'B', 'parent_id' => $a])->json('folder.id');
        // Moving A under its own descendant B would create a cycle → 422.
        $this->postJson(route('bookmarks.folders.move', $a), ['parent_id' => $b])->assertStatus(422);
        $this->assertNull(BookmarkFolder::find($a)->parent_id);
    }

    public function test_trash_restore_force_empty(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('bookmarks.store'), ['url' => 'https://a.test'])->json('bookmark.id');
        $this->deleteJson(route('bookmarks.destroy', $id))->assertOk();
        $this->getJson(route('bookmarks.list'))->assertJsonCount(0, 'bookmarks');
        $this->getJson(route('bookmarks.trash'))->assertJsonCount(1, 'bookmarks');
        $this->postJson(route('bookmarks.restore', $id))->assertOk();
        $this->getJson(route('bookmarks.list'))->assertJsonCount(1, 'bookmarks');
        $this->deleteJson(route('bookmarks.destroy', $id))->assertOk();
        $this->postJson(route('bookmarks.empty'))->assertOk();
        $this->assertNull(Bookmark::withTrashed()->find($id));
    }

    public function test_non_http_url_rejected_and_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        // javascript: URL is rejected by the http(s) regex rule — no row created.
        $this->actingAs($a)->postJson(route('bookmarks.store'), ['url' => 'javascript:alert(1)']);
        $this->assertSame(0, Bookmark::withoutGlobalScopes()->count());

        $this->actingAs($a)->postJson(route('bookmarks.store'), ['url' => 'https://secret.test'])->assertCreated();
        $this->actingAs($b)->getJson(route('bookmarks.list'))->assertJsonCount(0, 'bookmarks');
    }
}
