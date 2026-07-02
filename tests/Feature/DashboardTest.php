<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Files')
            ->assertSee('Storage used')
            ->assertSee('Recent files')
            ->assertSee('Toggle menu');
    }

    public function test_dashboard_shows_file_stats_and_recent_files(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create(['name' => 'Handbook.pdf', 'size' => 2048]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Handbook.pdf')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['files'] === 1 && $stats['storage'] === 2048);
    }
}
