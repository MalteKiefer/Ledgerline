<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_a_default_team_persists_and_activates_it(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user->teams()->attach([$teamA->id, $teamB->id]);
        $this->actingAs($user);

        $this->post(route('default-team.update'), ['team_id' => $teamB->id])->assertRedirect();

        $this->assertSame($teamB->id, $user->fresh()->default_team_id);
        $this->assertSame($teamB->id, session('active_team_id'));
    }

    public function test_cannot_set_a_default_team_you_do_not_belong_to(): void
    {
        $this->signIn();
        $foreign = Team::factory()->create();

        $this->post(route('default-team.update'), ['team_id' => $foreign->id])->assertForbidden();
    }

    public function test_current_team_prefers_the_persisted_default(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user->teams()->attach([$teamA->id, $teamB->id]);
        $user->forceFill(['default_team_id' => $teamB->id])->save();
        $user->forgetCachedTeamIds();

        $this->actingAs($user);

        $this->assertSame($teamB->id, $user->currentTeamId());
    }

    public function test_picker_is_hidden_when_a_default_team_is_set(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user->teams()->attach([$teamA->id, $teamB->id]);
        $user->forceFill(['default_team_id' => $teamA->id])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Choose your team');
    }
}
