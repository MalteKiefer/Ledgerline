<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_a_team_they_belong_to(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach([$teamA->id, $teamB->id]);
        session(['active_team_id' => $teamA->id]);
        $this->actingAs($user);

        $this->post(route('active-team.update'), ['team_id' => $teamB->id])->assertRedirect();

        // New records now belong to the newly activated team.
        $customer = Customer::factory()->create();
        $this->assertSame($teamB->id, $customer->team_id);
    }

    public function test_user_cannot_switch_to_a_team_they_do_not_belong_to(): void
    {
        $this->signIn();
        $foreign = Team::factory()->create();

        $this->post(route('active-team.update'), ['team_id' => $foreign->id])->assertForbidden();
    }
}
