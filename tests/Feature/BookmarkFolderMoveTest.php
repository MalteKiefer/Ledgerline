<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkFolderMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_bookmark_into_folder_and_folder_nesting(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $parent = BookmarkFolder::create(['name' => 'Parent']);
        $child = BookmarkFolder::create(['name' => 'Child']);
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test']);

        $this->postJson(route('bookmarks.move', $b->id), ['folder_id' => $parent->id])->assertOk();
        $this->assertSame($parent->id, $b->refresh()->bookmark_folder_id);

        $this->postJson(route('bookmarks.folders.move', $child->id), ['parent_id' => $parent->id])->assertOk();
        $this->assertSame($parent->id, $child->refresh()->parent_id);
    }

    public function test_cycle_is_rejected(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $a = BookmarkFolder::create(['name' => 'A']);
        $b = BookmarkFolder::create(['name' => 'B', 'parent_id' => $a->id]);

        // Making A a child of B would create a cycle.
        $this->postJson(route('bookmarks.folders.move', $a->id), ['parent_id' => $b->id])->assertStatus(422);
    }

    public function test_cannot_move_into_another_users_folder(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $theirs = BookmarkFolder::create(['name' => 'Theirs']);

        $this->actingAs($me);
        $b = Bookmark::create(['title' => 'X', 'url' => 'https://x.test']);
        $this->postJson(route('bookmarks.move', $b->id), ['folder_id' => $theirs->id]);
        // Security property: the bookmark is NOT moved into another user's folder.
        $this->assertNull($b->refresh()->bookmark_folder_id);
    }
}
