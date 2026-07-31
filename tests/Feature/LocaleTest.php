<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_browser_language_is_used_by_default(): void
    {
        $this->signIn();

        // The dashboard body is client-rendered (Alpine, after vault unlock), so the
        // server-side locale is asserted on the <html lang> attribute the layout sets.
        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="de"', false);
    }

    public function test_an_unsupported_browser_language_falls_back_to_english(): void
    {
        $this->signIn();

        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_a_user_can_switch_and_it_is_remembered(): void
    {
        $user = $this->signIn();

        $this->post(route('locale.update'), ['locale' => 'de'])->assertRedirect();

        $this->assertSame('de', $user->fresh()->locale);
        $this->get(route('dashboard'))->assertSee('lang="de"', false);
    }

    public function test_switching_rejects_an_unsupported_locale(): void
    {
        $this->signIn();

        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }
}
