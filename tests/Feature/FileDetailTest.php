<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_can_be_set_and_removed_from_the_detail_page(): void
    {
        $this->signIn();
        $file = File::factory()->create();
        $file->tags()->attach(Tag::findOrCreateByName('Old')->id);

        // Replace the tags.
        $this->put(route('files.update', $file), ['tags' => ['Invoice', 'Contract']])
            ->assertRedirect(route('files.show', $file));

        $this->assertEqualsCanonicalizing(['Contract', 'Invoice'], $file->fresh()->tags->pluck('name')->all());

        // Clear all tags.
        $this->put(route('files.update', $file), ['tags' => []]);
        $this->assertCount(0, $file->fresh()->tags);
    }

    public function test_detail_page_shows_no_encryption_hint(): void
    {
        $this->signIn();
        $file = File::factory()->create();

        $this->get(route('files.show', $file))
            ->assertOk()
            ->assertDontSee('Encrypted')
            ->assertDontSee('Verschlüsselt');
    }

    public function test_detail_page_shows_metadata(): void
    {
        $this->signIn();
        $file = File::factory()->create(['name' => 'Report.pdf']);

        $this->get(route('files.show', $file))
            ->assertOk()
            ->assertSee('Report.pdf')
            ->assertSee('Checksum');
    }

    public function test_metadata_can_be_edited(): void
    {
        $this->signIn();
        $file = File::factory()->create();

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
        $file = File::factory()->create(['name' => 'raw.pdf', 'title' => null]);

        $this->assertSame('raw.pdf', $file->displayTitle);

        $file->update(['title' => 'Nice Title']);
        $this->assertSame('Nice Title', $file->fresh()->displayTitle);
    }
}
