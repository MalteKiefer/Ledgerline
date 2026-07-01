<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_users_teams(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create(['name' => 'alpha_team']);
        $teamB = Team::factory()->create(['name' => 'beta_team']);
        $user->teams()->attach([$teamA->id, $teamB->id]);

        $this->actingAs($user)
            ->get(route('settings.teams.index'))
            ->assertOk()
            ->assertSee('Alpha Team')
            ->assertSee('Beta Team');
    }

    public function test_reassign_moves_data_between_the_users_teams(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user->teams()->attach([$teamA->id, $teamB->id]);
        $this->actingAs($user);

        $customer = Customer::factory()->create(['team_id' => $teamA->id]);

        $this->post(route('settings.teams.reassign'), ['from_team_id' => $teamA->id, 'to_team_id' => $teamB->id])
            ->assertRedirect(route('settings.teams.index'));

        $this->assertSame($teamB->id, $customer->fresh()->team_id);
    }

    public function test_reassign_requires_membership_of_both_teams(): void
    {
        $this->signIn();
        $foreign = Team::factory()->create();

        $this->post(route('settings.teams.reassign'), ['from_team_id' => $this->team->id, 'to_team_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_reassign_rejects_the_same_team(): void
    {
        $this->signIn();

        $this->post(route('settings.teams.reassign'), ['from_team_id' => $this->team->id, 'to_team_id' => $this->team->id])
            ->assertSessionHasErrors('from_team_id');
    }
}
