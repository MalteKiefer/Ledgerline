<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_settings(): void
    {
        $this->get(route('settings.tags.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_only_the_active_teams_tags(): void
    {
        $this->signIn();
        Tag::findOrCreateByName('Alpha');
        Tag::factory()->create(['team_id' => Team::factory()->create()->id, 'name' => 'Betaforeign', 'slug' => 'betaforeign']);

        $this->get(route('settings.tags.index'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Betaforeign');
    }

    public function test_can_add_a_tag_with_a_colour(): void
    {
        $this->signIn();

        $this->post(route('settings.tags.store'), ['name' => 'Invoice', 'color' => '#EF4444'])
            ->assertRedirect(route('settings.tags.index'));

        $this->assertDatabaseHas('tags', [
            'team_id' => $this->team->id,
            'name' => 'Invoice',
            'slug' => 'invoice',
            'color' => '#EF4444',
        ]);
    }

    public function test_add_rejects_a_duplicate_name(): void
    {
        $this->signIn();
        Tag::findOrCreateByName('Duplicate');

        $this->post(route('settings.tags.store'), ['name' => 'duplicate'])
            ->assertSessionHasErrors('name');
    }

    public function test_add_rejects_an_invalid_colour(): void
    {
        $this->signIn();

        $this->post(route('settings.tags.store'), ['name' => 'Bad', 'color' => 'red'])
            ->assertSessionHasErrors('color');
    }

    public function test_can_rename_and_recolour(): void
    {
        $this->signIn();
        $tag = Tag::findOrCreateByName('Old');

        $this->put(route('settings.tags.update', $tag), ['name' => 'New Name', 'color' => '#3B82F6'])
            ->assertRedirect(route('settings.tags.index'));

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'color' => '#3B82F6',
        ]);
    }

    public function test_can_delete_a_tag_and_detach_it(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $tag = Tag::findOrCreateByName('Removable');
        $project->tags()->attach($tag->id);

        $this->delete(route('settings.tags.destroy', $tag))
            ->assertRedirect(route('settings.tags.index'));

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertSame(0, $project->tags()->count());
    }

    public function test_cannot_edit_another_teams_tag(): void
    {
        $this->signIn();
        $foreign = Tag::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->put(route('settings.tags.update', $foreign), ['name' => 'Hijack'])->assertNotFound();
    }
}
