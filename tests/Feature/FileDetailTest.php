<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_page_shows_metadata(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['name' => 'Report.pdf']);

        $this->get(route('files.show', $file))
            ->assertOk()
            ->assertSee('Report.pdf')
            ->assertSee('Checksum');
    }

    public function test_metadata_can_be_edited(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create();

        $this->put(route('files.update', $file), [
            'title' => 'Signed Contract',
            'description' => 'The final version.',
            'note' => 'Keep for 10 years.',
        ])->assertRedirect(route('files.show', $file));

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'title' => 'Signed Contract',
            'description' => 'The final version.',
            'note' => 'Keep for 10 years.',
        ]);
    }

    public function test_display_title_falls_back_to_name(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $file = File::factory()->forCustomer($customer)->create(['name' => 'raw.pdf', 'title' => null]);

        $this->assertSame('raw.pdf', $file->displayTitle);

        $file->update(['title' => 'Nice Title']);
        $this->assertSame('Nice Title', $file->fresh()->displayTitle);
    }
}
