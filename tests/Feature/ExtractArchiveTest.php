<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExtractArchive;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\ArchiveManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtractArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_unpacks_with_status(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $this->actingAs($u);
        $tmp = tempnam(sys_get_temp_dir(), 'z').'.zip';
        $za = new \ZipArchive;
        $za->open($tmp, \ZipArchive::CREATE);
        $za->addFromString('a.txt', 'hello');
        $za->addFromString('sub/b.txt', 'world');
        $za->close();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, file_get_contents($tmp));
        @unlink($tmp);
        $zip = new StoredFile;
        $zip->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'pack.zip', 'blob' => $blob, 'size' => 100, 'mime' => 'application/zip'])->save();

        $res = $this->postJson(route('files.extract', $zip))->assertStatus(202);
        $token = $res->json('token');
        (new ExtractArchive($token, $u->id, $zip->id, 'pack.zip'))->handle(app(ArchiveManager::class));

        $this->assertDatabaseHas('files', ['user_id' => $u->id, 'name' => 'a.txt']);
        $this->assertDatabaseHas('files', ['user_id' => $u->id, 'name' => 'b.txt']);
        $this->assertSame('done', Cache::get(ExtractArchive::statusKey($token))['state']);
    }

    public function test_status_owner_scoped(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        Cache::put(ExtractArchive::statusKey('tok'), ['state' => 'running', 'user' => $u->id], now()->addHour());
        $this->actingAs($other);
        $this->getJson(route('files.extract.status', 'tok'))->assertNotFound();
    }
}
