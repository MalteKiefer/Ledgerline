<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_toggle_and_exposed_in_data(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'a.txt', 'blob' => $blob, 'size' => 1, 'mime' => 'text/plain'])->save();

        $this->actingAs($u)->postJson(route('files.favorite'), ['file_ids' => [$f->id], 'favorite' => true])->assertOk();
        $this->assertTrue(StoredFile::withoutGlobalScopes()->find($f->id)->favorite);

        $this->actingAs($u)->getJson(route('files.data'))
            ->assertOk()->assertJsonPath('files.0.favorite', true);

        $this->actingAs($u)->postJson(route('files.favorite'), ['file_ids' => [$f->id], 'favorite' => false])->assertOk();
        $this->assertFalse(StoredFile::withoutGlobalScopes()->find($f->id)->favorite);
    }
}
