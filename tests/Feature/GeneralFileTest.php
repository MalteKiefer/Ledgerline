<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FileType;
use App\Models\Customer;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_upload_creates_a_file_without_an_owner_in_a_folder(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Company']);

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->create('letterhead.pdf', 12, 'application/pdf')],
            'folder_id' => $folder->id,
        ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

        $file = File::firstWhere('name', 'letterhead.pdf');
        $this->assertNull($file->attachable_type);
        $this->assertTrue($file->isGeneral());
        $this->assertSame($folder->id, $file->folder_id);
    }

    public function test_a_dropped_folder_recreates_its_subfolders(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [
                UploadedFile::fake()->create('a.txt', 5),
                UploadedFile::fake()->create('b.txt', 5),
            ],
            'paths' => ['Trip/Day1/a.txt', 'Trip/b.txt'],
        ])->assertRedirect();

        $trip = Folder::where('name', 'Trip')->whereNull('parent_id')->first();
        $this->assertNotNull($trip);
        $day1 = Folder::where('name', 'Day1')->where('parent_id', $trip->id)->first();
        $this->assertNotNull($day1);

        $this->assertSame($day1->id, File::firstWhere('name', 'a.txt')->folder_id);
        $this->assertSame($trip->id, File::firstWhere('name', 'b.txt')->folder_id);
    }

    public function test_same_name_uploads_respect_the_conflict_strategy(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Docs']);
        $route = route('files.store.general');
        $upload = fn () => ['files' => [UploadedFile::fake()->create('a.txt', 5)], 'folder_id' => $folder->id];

        $this->post($route, $upload());
        $this->assertSame(1, File::where('folder_id', $folder->id)->count());

        // Overwrite keeps a single file with the original name.
        $this->post($route, $upload() + ['on_conflict' => 'overwrite']);
        $this->assertSame(1, File::where('folder_id', $folder->id)->where('name', 'a.txt')->count());

        // Rename keeps both.
        $this->post($route, $upload() + ['on_conflict' => 'rename']);
        $this->assertSame(1, File::where('folder_id', $folder->id)->where('name', 'a_2.txt')->count());
        $this->assertSame(2, File::where('folder_id', $folder->id)->count());

        // Skip adds nothing.
        $this->post($route, $upload() + ['on_conflict' => 'skip']);
        $this->assertSame(2, File::where('folder_id', $folder->id)->count());
    }

    public function test_a_zip_archive_is_extracted_into_a_folder(): void
    {
        Storage::fake('files');
        $this->signIn();

        $zipPath = tempnam(sys_get_temp_dir(), 'z').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('docs/readme.txt', 'hello');
        $zip->close();
        Storage::disk('files')->put('files/archive.zip', file_get_contents($zipPath));
        @unlink($zipPath);

        $file = File::factory()->create([
            'name' => 'archive.zip',
            'type' => FileType::ARCHIVE,
            'disk_path' => 'files/archive.zip',
            'attachable_type' => null,
            'attachable_id' => null,
        ]);

        $this->post(route('files.extract', $file))->assertRedirect();

        $base = Folder::where('name', 'archive')->whereNull('parent_id')->first();
        $this->assertNotNull($base);
        $docs = Folder::where('name', 'docs')->where('parent_id', $base->id)->first();
        $this->assertNotNull($docs);
        $this->assertSame($docs->id, File::firstWhere('name', 'readme.txt')->folder_id);
    }

    public function test_conflicts_endpoint_reports_existing_paths(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Docs']);
        File::factory()->create(['name' => 'a.txt', 'folder_id' => $folder->id, 'attachable_type' => null, 'attachable_id' => null]);

        $this->postJson(route('files.conflicts'), [
            'paths' => ['a.txt', 'b.txt'],
            'folder_id' => $folder->id,
        ])->assertOk()->assertJson(['conflicts' => ['a.txt']]);
    }

    public function test_a_file_can_be_moved_into_a_folder(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Contracts']);
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create();

        $this->put(route('files.update', $file), ['folder_id' => $folder->id])
            ->assertRedirect(route('files.show', $file));

        $this->assertSame($folder->id, $file->fresh()->folder_id);
    }

    public function test_browser_shows_a_folders_subfolders_and_files(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Contracts']);
        Folder::create(['name' => 'Twenty-Six', 'parent_id' => $folder->id]);
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create(['name' => 'signed.pdf', 'folder_id' => $folder->id]);
        File::factory()->forCustomer($customer)->create(['name' => 'rootfile.pdf']);

        $this->get(route('files.index', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSee('Twenty-Six')
            ->assertSee('signed.pdf')
            ->assertDontSee('rootfile.pdf');
    }

    public function test_root_lists_unfiled_files(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create(['name' => 'rootfile.pdf']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('rootfile.pdf');
    }
}
