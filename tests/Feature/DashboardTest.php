<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_dashboard_with_summary_cards(): void
    {
        $this->signIn();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Customers')
            ->assertSee('Projects')
            ->assertSee('Files')
            ->assertSee('Storage used')
            ->assertSee('Recent files')
            ->assertSee('Toggle menu');
    }

    public function test_counts_reflect_only_the_users_team(): void
    {
        $this->signIn();
        $customers = Customer::factory()->count(2)->create();
        Project::factory()->count(3)->for($customers->first())->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['customers'] === 2 && $stats['projects'] === 3);
    }

    public function test_dashboard_shows_file_stats_and_recent_files(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create(['name' => 'Handbook.pdf', 'size' => 2048]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Handbook.pdf')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['files'] === 1 && $stats['storage'] === 2048);
    }
}
