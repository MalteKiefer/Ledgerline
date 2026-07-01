<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamReassignCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_all_owned_data_to_the_target_team(): void
    {
        $from = Team::factory()->create();
        $to = Team::factory()->create();

        $customer = Customer::factory()->create(['team_id' => $from->id]);
        $contact = Contact::factory()->for($customer)->create();
        $project = Project::factory()->for($customer)->create();

        $this->artisan('teams:reassign', ['from' => $from->id, 'to' => $to->id])
            ->assertSuccessful();

        $this->assertSame($to->id, $customer->fresh()->team_id);
        $this->assertSame($to->id, $contact->fresh()->team_id);
        $this->assertSame($to->id, $project->fresh()->team_id);
    }

    public function test_it_can_move_members_too(): void
    {
        $from = Team::factory()->create();
        $to = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($from);

        $this->artisan('teams:reassign', ['from' => $from->key, 'to' => $to->key, '--with-members' => true])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->teams()->whereKey($to->id)->exists());
    }

    public function test_it_fails_for_an_unknown_team(): void
    {
        $to = Team::factory()->create();

        $this->artisan('teams:reassign', ['from' => 'does-not-exist', 'to' => $to->id])
            ->assertFailed();
    }
}
