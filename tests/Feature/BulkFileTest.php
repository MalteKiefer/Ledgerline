<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
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
        $customer = Customer::factory()->create();
        $folder = Folder::create(['name' => 'Archive']);
        $a = File::factory()->forCustomer($customer)->create();
        $b = File::factory()->forCustomer($customer)->create();

        $this->post(route('files.bulk.move'), ['file_ids' => [$a->id, $b->id], 'folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertSame($folder->id, $a->fresh()->folder_id);
        $this->assertSame($folder->id, $b->fresh()->folder_id);
    }

    public function test_bulk_move_to_root_clears_the_folder(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $folder = Folder::create(['name' => 'X']);
        $file = File::factory()->forCustomer($customer)->create(['folder_id' => $folder->id]);

        $this->post(route('files.bulk.move'), ['file_ids' => [$file->id], 'folder_id' => ''])->assertRedirect();

        $this->assertNull($file->fresh()->folder_id);
    }

    public function test_bulk_delete_removes_all_selected_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();
        $a = File::factory()->forCustomer($customer)->create();
        $b = File::factory()->forCustomer($customer)->create();

        $this->post(route('files.bulk.delete'), ['file_ids' => [$a->id, $b->id]])->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $a->id]);
        $this->assertDatabaseMissing('files', ['id' => $b->id]);
    }

    public function test_bulk_requires_at_least_one_file(): void
    {
        $this->signIn();
        $this->post(route('files.bulk.move'), ['folder_id' => ''])->assertSessionHasErrors('file_ids');
    }
}
