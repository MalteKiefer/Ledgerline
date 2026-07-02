<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_move_sets_the_folder_on_all_selected_files(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Archive']);
        $a = File::factory()->create();
        $b = File::factory()->create();

        $this->post(route('files.bulk.move'), ['file_ids' => [$a->id, $b->id], 'folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertSame($folder->id, $a->fresh()->folder_id);
        $this->assertSame($folder->id, $b->fresh()->folder_id);
    }

    public function test_bulk_move_to_root_clears_the_folder(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'X']);
        $file = File::factory()->create(['folder_id' => $folder->id]);

        $this->post(route('files.bulk.move'), ['file_ids' => [$file->id], 'folder_id' => ''])->assertRedirect();

        $this->assertNull($file->fresh()->folder_id);
    }

    public function test_bulk_delete_removes_all_selected_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        $a = File::factory()->create();
        $b = File::factory()->create();

        $this->post(route('files.bulk.delete'), ['file_ids' => [$a->id, $b->id]])->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $a->id]);
        $this->assertDatabaseMissing('files', ['id' => $b->id]);
    }

    public function test_bulk_requires_at_least_one_file(): void
    {
        $this->signIn();
        $this->post(route('files.bulk.move'), ['folder_id' => ''])->assertSessionHasErrors('file_ids');
    }

    public function test_bulk_move_reparents_selected_folders(): void
    {
        $this->signIn();
        $target = Folder::create(['name' => 'Target']);
        $a = Folder::create(['name' => 'A']);
        $b = Folder::create(['name' => 'B']);

        $this->post(route('files.bulk.move'), ['folder_ids' => [$a->id, $b->id], 'folder_id' => $target->id])
            ->assertRedirect();

        $this->assertSame($target->id, $a->fresh()->parent_id);
        $this->assertSame($target->id, $b->fresh()->parent_id);
    }

    public function test_bulk_move_refuses_to_move_a_folder_into_its_own_descendant(): void
    {
        $this->signIn();
        $parent = Folder::create(['name' => 'Parent']);
        $child = Folder::create(['name' => 'Child', 'parent_id' => $parent->id]);

        // Moving Parent into Child would create a cycle; it must be skipped.
        $this->post(route('files.bulk.move'), ['folder_ids' => [$parent->id], 'folder_id' => $child->id])
            ->assertRedirect();

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_bulk_delete_removes_folders_and_lifts_their_contents_up(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Doomed']);
        $file = File::factory()->create(['folder_id' => $folder->id]);

        $this->post(route('files.bulk.delete'), ['folder_ids' => [$folder->id]])->assertRedirect();

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        // The file survives, moved up to the (root) parent.
        $this->assertNull($file->fresh()->folder_id);
    }

    public function test_download_manifest_includes_selected_files_and_folder_descendants(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Trip']);
        $sub = Folder::create(['name' => 'Day1', 'parent_id' => $folder->id]);
        $loose = File::factory()->create(['name' => 'loose.txt']);
        $inSub = File::factory()->create(['name' => 'deep.txt', 'folder_id' => $sub->id]);
        $other = File::factory()->create(['name' => 'other.txt']);

        $res = $this->postJson(route('files.bulk.manifest'), [
            'file_ids' => [$loose->id],
            'folder_ids' => [$folder->id],
        ])->assertOk();

        $ids = collect($res->json('files'))->pluck('id')->all();
        $this->assertContains($loose->id, $ids);   // selected file
        $this->assertContains($inSub->id, $ids);    // descendant of a selected folder
        $this->assertNotContains($other->id, $ids); // unrelated
    }

    public function test_descendants_lists_plaintext_items_in_the_subtree(): void
    {
        $this->signIn();
        $root = Folder::create(['name' => 'Root']);
        $sub = Folder::create(['name' => 'Sub', 'parent_id' => $root->id]);
        $enc = Folder::create(['name' => '', 'enc_name' => '{"c":"c","n":"n"}', 'parent_id' => $root->id]);
        $plain = File::factory()->create(['name' => 'a.txt', 'folder_id' => $sub->id, 'is_encrypted' => false]);
        File::factory()->create(['name' => '', 'folder_id' => $sub->id, 'is_encrypted' => true]);

        $res = $this->getJson(route('folders.descendants', $root))->assertOk();

        // Plaintext folders (root + sub) listed; the already-encrypted one omitted.
        $res->assertJsonFragment(['id' => $root->id, 'name' => 'Root'])
            ->assertJsonFragment(['id' => $sub->id, 'name' => 'Sub'])
            ->assertJsonMissing(['id' => $enc->id]);
        // Only the plaintext file is listed.
        $res->assertJsonFragment(['id' => $plain->id, 'name' => 'a.txt']);
    }
}
