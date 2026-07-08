<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicatesUiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: string, 1: Photo, 2: Photo} */
    private function group(): array
    {
        $gid = (string) Str::uuid();
        $a = Photo::factory()->create(['duplicate_group_id' => $gid, 'dup_score' => 0.98, 'name' => 'a.jpg', 'size' => 1000, 'status' => 'ready']);
        $b = Photo::factory()->create(['duplicate_group_id' => $gid, 'dup_score' => 0.98, 'name' => 'b.jpg', 'size' => 2000, 'status' => 'ready']);

        return [$gid, $a, $b];
    }

    public function test_data_lists_groups_with_name_and_size(): void
    {
        $this->signIn();
        [$gid] = $this->group();

        // Best copy first (largest bytes) so a keep-first default keeps the best.
        $this->getJson(route('gallery.duplicates.data'))
            ->assertOk()
            ->assertJsonPath('groups.0.group', $gid)
            ->assertJsonCount(2, 'groups.0.photos')
            ->assertJsonPath('groups.0.photos.0.name', 'b.jpg')
            ->assertJsonPath('groups.0.photos.0.size', 2000)
            ->assertJsonPath('groups.0.photos.1.name', 'a.jpg');
    }

    public function test_resolve_keeps_one_and_trashes_the_rest(): void
    {
        Storage::fake('files');
        $this->signIn();
        [$gid, $a, $b] = $this->group();

        $this->postJson(route('gallery.duplicates.resolve', ['group' => $gid]), ['keep_id' => $a->id])
            ->assertOk()->assertJson(['ok' => true, 'kept' => $a->id]);

        $this->assertNull($a->fresh()->duplicate_group_id);
        $this->assertNotSoftDeleted('photos', ['id' => $a->id]);
        $this->assertSoftDeleted('photos', ['id' => $b->id]);
    }

    public function test_resolve_rejects_a_keep_id_outside_the_group(): void
    {
        $this->signIn();
        [$gid] = $this->group();
        $other = Photo::factory()->create();

        $this->postJson(route('gallery.duplicates.resolve', ['group' => $gid]), ['keep_id' => $other->id])
            ->assertStatus(422);
    }

    public function test_dismiss_marks_the_group_not_a_duplicate(): void
    {
        $this->signIn();
        [$gid, $a, $b] = $this->group();

        $this->postJson(route('gallery.duplicates.dismiss', ['group' => $gid]))
            ->assertOk()->assertJson(['ok' => true]);

        foreach ([$a, $b] as $p) {
            $fresh = $p->fresh();
            $this->assertNull($fresh->duplicate_group_id);
            $this->assertNotNull($fresh->dup_dismissed_at);
        }
    }

    public function test_empty_when_no_duplicates(): void
    {
        $this->signIn();
        Photo::factory()->create();

        $this->getJson(route('gallery.duplicates.data'))->assertOk()->assertJsonCount(0, 'groups');
    }
}
