<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FileType;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_upload(): void
    {
        $customer = Customer::factory()->create();

        $this->post(route('customers.files.store', $customer), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('login'));
    }

    public function test_upload_stores_a_file_for_a_customer_with_type_and_tags(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.files.store', $customer), [
            'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
            'tags' => ['Contract', 'Signed'],
        ])->assertRedirect(route('customers.show', $customer));

        $file = File::firstWhere('name', 'contract.pdf');

        $this->assertNotNull($file);
        $this->assertSame(FileType::PDF, $file->type);
        $this->assertSame($customer->team_id, $file->team_id);
        $this->assertTrue($file->attachable->is($customer));
        $this->assertEqualsCanonicalizing(['Contract', 'Signed'], $file->tags->pluck('name')->all());
        Storage::disk('files')->assertExists($file->disk_path);
    }

    public function test_upload_extracts_text_from_plain_text_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.files.store', $customer), [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'the secret keyword is qzxwv'),
        ]);

        $file = File::firstWhere('name', 'notes.txt');
        $this->assertStringContainsString('qzxwv', (string) $file->extracted_text);
    }

    public function test_upload_does_not_extract_binary_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.files.store', $customer), [
            'file' => UploadedFile::fake()->create('bundle.zip', 10, 'application/zip'),
        ]);

        $this->assertNull(File::firstWhere('name', 'bundle.zip')->extracted_text);
    }

    public function test_upload_stores_a_file_for_a_project(): void
    {
        Storage::fake('files');
        $this->signIn();
        $project = Project::factory()->create();

        $this->post(route('projects.files.store', $project), [
            'file' => UploadedFile::fake()->image('diagram.png'),
        ])->assertRedirect(route('projects.show', $project));

        $file = File::firstWhere('name', 'diagram.png');
        $this->assertSame(FileType::IMAGE, $file->type);
        $this->assertTrue($file->attachable->is($project));
    }

    public function test_download_streams_a_file(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['disk_path' => 'files/doc.pdf']);
        Storage::disk('files')->put('files/doc.pdf', 'bytes');

        $this->get(route('files.download', $file))->assertOk();
    }

    public function test_destroy_deletes_file_and_bytes(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['disk_path' => 'files/doc.pdf']);
        Storage::disk('files')->put('files/doc.pdf', 'bytes');

        $this->delete(route('files.destroy', $file))->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('files')->assertMissing('files/doc.pdf');
    }

    public function test_cannot_download_another_teams_file(): void
    {
        Storage::fake('files');
        $this->signIn();
        $foreignCustomer = Customer::factory()->create(['team_id' => Team::factory()->create()->id]);
        $foreignFile = File::factory()->forCustomer($foreignCustomer)->create();

        $this->get(route('files.download', $foreignFile))->assertNotFound();
    }

    public function test_cannot_upload_to_another_teams_customer(): void
    {
        Storage::fake('files');
        $this->signIn();
        $foreignCustomer = Customer::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->post(route('customers.files.store', $foreignCustomer), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
    }

    public function test_overview_lists_only_own_team_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create(['name' => 'Mine.pdf']);

        $foreignCustomer = Customer::factory()->create(['team_id' => Team::factory()->create()->id]);
        File::factory()->forCustomer($foreignCustomer)->create(['name' => 'Theirs.pdf']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('Mine.pdf')
            ->assertDontSee('Theirs.pdf');
    }

    public function test_search_finds_files_by_name_and_content(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create([
            'name' => 'Onboarding.txt',
            'extracted_text' => 'welcome qzxwvcontent handbook',
        ]);

        $this->get(route('search', ['q' => 'qzxwvcontent']))
            ->assertOk()
            ->assertSee('Files')
            ->assertSee('Onboarding.txt');
    }
}
