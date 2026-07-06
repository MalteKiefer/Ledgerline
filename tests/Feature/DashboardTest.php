<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
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
        Photo::factory()->count(2)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertViewHas('gallery', fn (array $gallery): bool => $gallery['total'] === 2);
    }

    public function test_dashboard_links_to_every_module(): void
    {
        $this->signIn();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('files.index'))
            ->assertSee(route('notes.index'))
            ->assertSee(route('bookmarks.index'));
    }
}
