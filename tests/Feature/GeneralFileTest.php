<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            'file' => UploadedFile::fake()->create('letterhead.pdf', 12, 'application/pdf'),
            'folder_id' => $folder->id,
        ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

        $file = File::firstWhere('name', 'letterhead.pdf');
        $this->assertNull($file->attachable_type);
        $this->assertTrue($file->isGeneral());
        $this->assertSame($folder->id, $file->folder_id);
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
