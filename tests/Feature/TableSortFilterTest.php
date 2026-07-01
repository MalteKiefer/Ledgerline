<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TableSortFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_be_sorted_descending(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Alpha Co']);
        Customer::factory()->create(['name' => 'Zeta Co']);

        $this->get(route('customers.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zeta Co', 'Alpha Co']);
    }

    public function test_customers_can_be_searched(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Findme Industries']);
        Customer::factory()->create(['name' => 'Hidden Corp']);

        $this->get(route('customers.index', ['q' => 'findme']))
            ->assertOk()
            ->assertSee('Findme Industries')
            ->assertDontSee('Hidden Corp');
    }

    public function test_an_unsafe_sort_column_is_ignored(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Acme']);

        $this->get(route('customers.index', ['sort' => 'name; drop table customers']))
            ->assertOk()
            ->assertSee('Acme');
    }

    public function test_projects_overview_can_be_searched(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Findable Migration']);
        Project::factory()->create(['name' => 'Secret Thing']);

        $this->get(route('projects.overview', ['q' => 'findable']))
            ->assertOk()
            ->assertSee('Findable Migration')
            ->assertDontSee('Secret Thing');
    }

    public function test_files_can_be_filtered_by_tag(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();

        $tagged = File::factory()->forCustomer($customer)->create(['name' => 'Contract.pdf']);
        $tagged->tags()->attach(Tag::findOrCreateByName('Contract')->id);

        File::factory()->forCustomer($customer)->create(['name' => 'Untagged.pdf']);

        $this->get(route('files.index', ['tag' => 'contract']))
            ->assertOk()
            ->assertSee('Contract.pdf')
            ->assertDontSee('Untagged.pdf')
            ->assertSee('Tag: Contract');
    }

    public function test_file_tags_link_to_the_filtered_overview(): void
    {
        Storage::fake('files');
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create();
        $file->tags()->attach(Tag::findOrCreateByName('Invoice')->id);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee(route('files.index', ['tag' => 'invoice']), false);
    }
}
