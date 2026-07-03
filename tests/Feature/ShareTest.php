<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_a_share_snapshot(): void
    {
        $this->signIn();
        $note = Note::create(['title' => 'Recipe', 'content' => '# Pancakes']);

        $this->post(route('notes.share', $note), ['expires_in' => 86400])->assertRedirect();

        $share = NoteShare::first();
        $this->assertSame('Recipe', $share->title);
        $this->assertSame('# Pancakes', $share->content);
        $this->assertFalse($share->has_password);
    }

    public function test_public_can_view_a_share_rendered_as_html(): void
    {
        $share = NoteShare::create(['title' => 'Hello', 'content' => '# Big title', 'expires_at' => now()->addDay()]);

        $this->get(route('shares.show', $share))
            ->assertOk()->assertSee('Big title')->assertSee('<h1', false);
    }

    public function test_an_expired_share_is_gone(): void
    {
        $share = NoteShare::create(['title' => 'x', 'content' => 'y', 'expires_at' => now()->subMinute()]);

        $this->get(route('shares.show', $share))->assertStatus(410);
        $this->assertSame(0, NoteShare::count());
    }

    public function test_a_password_share_requires_the_password(): void
    {
        $share = NoteShare::create([
            'title' => 'Secret', 'content' => 'top secret body',
            'has_password' => true, 'password_hash' => Hash::make('open-sesame'),
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('shares.show', $share))->assertOk()->assertDontSee('top secret body');
        $this->post(route('shares.unlock', $share), ['password' => 'nope'])->assertStatus(422);
        $this->post(route('shares.unlock', $share), ['password' => 'open-sesame'])->assertRedirect(route('shares.show', $share));
        $this->get(route('shares.show', $share))->assertOk()->assertSee('top secret body');
    }

    public function test_view_limit_is_enforced(): void
    {
        $share = NoteShare::create(['title' => 'x', 'content' => 'once only', 'max_views' => 1, 'expires_at' => now()->addDay()]);

        $this->get(route('shares.show', $share))->assertOk()->assertSee('once only');
        $this->get(route('shares.show', $share))->assertStatus(410);
    }

    public function test_html_in_content_is_escaped(): void
    {
        $share = NoteShare::create(['title' => 'xss', 'content' => 'hello <script>alert(1)</script>', 'expires_at' => now()->addDay()]);

        $this->get(route('shares.show', $share))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
