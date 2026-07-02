<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TableSortFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unsafe_sort_column_is_ignored(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create(['name' => 'Acme.pdf']);

        $this->get(route('files.index', ['sort' => 'name; drop table files']))
            ->assertOk()
            ->assertSee('Acme.pdf');
    }

    public function test_files_can_be_filtered_by_tag(): void
    {
        Storage::fake('files');
        $this->signIn();

        $tagged = File::factory()->create(['name' => 'Contract.pdf']);
        $tagged->tags()->attach(Tag::findOrCreateByName('Contract')->id);

        File::factory()->create(['name' => 'Untagged.pdf']);

        $this->get(route('files.index', ['tag' => 'contract']))
            ->assertOk()
            ->assertSee('Contract.pdf')
            ->assertDontSee('Untagged.pdf')
            ->assertSee('#Contract');
    }

    public function test_file_tags_link_to_the_filtered_overview_from_the_detail_page(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create();
        $file->tags()->attach(Tag::findOrCreateByName('Invoice')->id);

        $this->get(route('files.show', $file))
            ->assertOk()
            ->assertSee(route('files.index', ['tag' => 'invoice']), false);
    }
}
