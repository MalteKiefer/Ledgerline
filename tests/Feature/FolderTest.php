<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_folders(): void
    {
        $this->post(route('folders.store'), ['name' => 'X'])->assertRedirect(route('login'));
    }

    public function test_can_create_a_folder_and_a_subfolder(): void
    {
        $this->signIn();

        $this->post(route('folders.store'), ['name' => 'Company'])->assertRedirect();
        $company = Folder::firstWhere('name', 'Company');
        $this->assertNull($company->parent_id);

        $this->post(route('folders.store'), ['name' => 'Logos', 'parent_id' => $company->id])->assertRedirect();
        $this->assertSame($company->id, Folder::firstWhere('name', 'Logos')->parent_id);
    }

    public function test_can_rename_a_folder(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Old']);

        $this->put(route('folders.update', $folder), ['name' => 'New'])->assertRedirect();

        $this->assertSame('New', $folder->fresh()->name);
    }

    public function test_can_create_a_folder_with_an_encrypted_name(): void
    {
        $this->signIn();

        $this->post(route('folders.store'), ['enc_name' => '{"c":"cipher","n":"nonce"}'])
            ->assertRedirect();

        $folder = Folder::sole();
        $this->assertSame('', $folder->name);
        $this->assertSame('{"c":"cipher","n":"nonce"}', $folder->enc_name);
    }

    public function test_creating_an_encrypted_folder_over_ajax_returns_its_id(): void
    {
        $this->signIn();

        $res = $this->postJson(route('folders.store'), ['enc_name' => '{"c":"c","n":"n"}'])
            ->assertCreated();

        $res->assertJsonStructure(['id', 'parent_id']);
        $this->assertSame(Folder::sole()->id, $res->json('id'));
    }

    public function test_renaming_to_an_encrypted_name_clears_the_plaintext_name(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Old']);

        $this->put(route('folders.update', $folder), ['enc_name' => '{"c":"c2","n":"n2"}'])
            ->assertRedirect();

        $folder->refresh();
        $this->assertSame('', $folder->name);
        $this->assertSame('{"c":"c2","n":"n2"}', $folder->enc_name);
    }

    public function test_deleting_a_folder_moves_its_contents_up(): void
    {
        $this->signIn();
        $parent = Folder::create(['name' => 'Parent']);
        $child = Folder::create(['name' => 'Child', 'parent_id' => $parent->id]);
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['folder_id' => $parent->id]);

        $this->delete(route('folders.destroy', $parent))->assertRedirect();

        $this->assertDatabaseMissing('folders', ['id' => $parent->id]);
        $this->assertNull($child->fresh()->parent_id);
        $this->assertNull($file->fresh()->folder_id);
    }
}
