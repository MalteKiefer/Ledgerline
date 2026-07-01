<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileExplorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_upload_stores_every_file(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            ],
        ])->assertRedirect(route('files.index', ['folder' => null]));

        $this->assertSame(3, File::count());
    }

    public function test_rename_sets_the_display_title(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['name' => 'raw.pdf', 'title' => null]);

        $this->put(route('files.rename', $file), ['title' => 'Signed contract'])->assertRedirect();

        $this->assertSame('Signed contract', $file->fresh()->title);
    }

    public function test_files_can_be_filtered_by_customer_and_project(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();
        File::factory()->forCustomer($customer)->create(['name' => 'CustomerDoc.pdf']);
        File::factory()->forProject($project)->create(['name' => 'ProjectDoc.pdf']);

        $this->get(route('files.index', ['customer' => $customer->id]))
            ->assertOk()->assertSee('CustomerDoc.pdf')->assertDontSee('ProjectDoc.pdf');

        $this->get(route('files.index', ['project' => $project->id]))
            ->assertOk()->assertSee('ProjectDoc.pdf')->assertDontSee('CustomerDoc.pdf');
    }

    public function test_upload_within_a_customer_filter_attaches_to_that_customer(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')],
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $file = File::firstWhere('name', 'note.pdf');
        $this->assertTrue($file->attachable->is($customer));
    }
}
