<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_team_user_without_an_active_team_sees_the_picker(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach([Team::factory()->create()->id, Team::factory()->create()->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Choose your team');
    }

    public function test_picker_shows_humanised_names_in_alphabetical_order(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach([
            Team::factory()->create(['name' => 'mail_admins'])->id,
            Team::factory()->create(['name' => 'admin'])->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mail Admins')
            ->assertDontSee('mail_admins')
            ->assertSeeInOrder(['Admin', 'Mail Admins']);
    }

    public function test_picker_is_hidden_once_a_team_is_active(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $user->teams()->attach([$teamA->id, Team::factory()->create()->id]);
        session(['active_team_id' => $teamA->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Choose your team');
    }

    public function test_single_team_user_never_sees_the_picker(): void
    {
        $this->signIn(); // single team, active set

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Choose your team');
    }
}
