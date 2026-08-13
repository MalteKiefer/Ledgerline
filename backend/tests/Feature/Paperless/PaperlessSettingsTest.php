<?php

declare(strict_types=1);

namespace Tests\Feature\Paperless;

use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperlessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_loads(): void
    {
        $this->signIn();
        $this->get(route('settings.paperless.edit'))->assertOk();
    }

    public function test_a_metadata_url_is_rejected(): void
    {
        $this->signIn();

        $this->put(route('settings.paperless.update'), [
            'paperless_enabled' => '1',
            'paperless_url' => 'http://169.254.169.254/latest/meta-data/',
            'paperless_token' => 'secret-token',
        ])->assertSessionHasErrors('paperless_url');

        $this->assertNull(UserSetting::for(auth()->id())->paperless_url);
    }

    public function test_it_saves_url_and_token_encrypted(): void
    {
        $this->signIn();

        $this->put(route('settings.paperless.update'), [
            'paperless_enabled' => '1',
            'paperless_url' => 'https://paperless.example.com',
            'paperless_token' => 'secret-token',
        ])->assertRedirect(route('settings.paperless.edit'));

        $settings = UserSetting::for(auth()->id());
        $this->assertTrue($settings->paperless_enabled);
        $this->assertSame('https://paperless.example.com', $settings->paperless_url);
        $this->assertSame('secret-token', $settings->paperless_token);

        // The raw column must not contain the plaintext token.
        $raw = DB::table('user_settings')->where('user_id', auth()->id())->value('paperless_token');
        $this->assertStringNotContainsString('secret-token', (string) $raw);
    }

    public function test_a_blank_token_keeps_the_stored_one(): void
    {
        $this->signIn();
        UserSetting::for(auth()->id())->update(['paperless_token' => 'keep-me', 'paperless_url' => 'https://p.example.com']);

        $this->put(route('settings.paperless.update'), [
            'paperless_enabled' => '1',
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => '',
        ])->assertRedirect();

        $this->assertSame('keep-me', UserSetting::for(auth()->id())->paperless_token);
    }

    public function test_it_rejects_an_invalid_url(): void
    {
        $this->signIn();

        $this->put(route('settings.paperless.update'), [
            'paperless_url' => 'not-a-url',
        ])->assertSessionHasErrors('paperless_url');
    }

    public function test_the_connection_test_reports_success(): void
    {
        $this->signIn();
        Http::fake([
            '*/api/documents/*' => Http::response(['count' => 3, 'results' => []], 200),
            '*/api/tags/*' => Http::response(['count' => 12, 'results' => []], 200),
            '*/api/document_types/*' => Http::response(['count' => 4, 'results' => []], 200),
            '*/api/correspondents/*' => Http::response(['count' => 8, 'results' => []], 200),
        ]);

        $this->postJson(route('settings.paperless.test'), [
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => 'tok',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_the_connection_test_reports_auth_failure(): void
    {
        $this->signIn();
        Http::fake(['*/api/documents/*' => Http::response([], 403)]);

        $this->postJson(route('settings.paperless.test'), [
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => 'bad',
        ])->assertOk()->assertJson(['ok' => false]);
    }
}
