<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PaperlessTerm;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mobile Paperless API — GET/POST /api/v1/paperless/*.
 *
 * All endpoints require a Sanctum device token (abilities:device).
 * Tests mock/avoid live Paperless HTTP calls; the focus is on
 * auth gates, validation, owner-scope, and the service call contract.
 */
class PaperlessApiTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function deviceToken(User $user): string
    {
        return $user->createToken('iphone', ['device'])->plainTextToken;
    }

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->deviceToken($user)];
    }

    /** Seed a UserSetting row with Paperless enabled + fake credentials. */
    private function enablePaperless(User $user): void
    {
        UserSetting::for($user->id)->update([
            'paperless_enabled' => true,
            'paperless_url' => 'http://paperless.local',
            'paperless_token' => 'faketoken',
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/paperless/terms
    // -----------------------------------------------------------------------

    public function test_terms_requires_device_token(): void
    {
        $this->getJson('/api/v1/paperless/terms')->assertUnauthorized();
    }

    public function test_terms_returns_configured_false_when_paperless_not_configured(): void
    {
        $user = User::factory()->create();
        // No Paperless URL/token — configured must be false.

        $this->getJson('/api/v1/paperless/terms', $this->authHeader($user))
            ->assertOk()
            ->assertJson([
                'configured' => false,
                'tags' => [],
                'document_types' => [],
                'correspondents' => [],
            ]);
    }

    public function test_terms_returns_configured_true_and_cached_terms(): void
    {
        $user = User::factory()->create();
        $this->enablePaperless($user);

        // Seed some cached terms (owned by this user).
        PaperlessTerm::create(['user_id' => $user->id, 'kind' => 'tag',           'paperless_id' => 1, 'name' => 'Alpha', 'color' => '#ff0000']);
        PaperlessTerm::create(['user_id' => $user->id, 'kind' => 'document_type', 'paperless_id' => 2, 'name' => 'Invoice', 'color' => null]);
        PaperlessTerm::create(['user_id' => $user->id, 'kind' => 'correspondent', 'paperless_id' => 3, 'name' => 'Acme', 'color' => null]);

        $response = $this->getJson('/api/v1/paperless/terms', $this->authHeader($user))
            ->assertOk()
            ->assertJsonPath('configured', true);

        $this->assertCount(1, $response->json('tags'));
        $this->assertSame('Alpha', $response->json('tags.0.name'));
        $this->assertCount(1, $response->json('document_types'));
        $this->assertCount(1, $response->json('correspondents'));
    }

    public function test_terms_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->enablePaperless($a);
        $this->enablePaperless($b);

        PaperlessTerm::create(['user_id' => $a->id, 'kind' => 'tag', 'paperless_id' => 10, 'name' => 'OnlyA', 'color' => null]);

        // User B must not see user A's terms.
        $this->getJson('/api/v1/paperless/terms', $this->authHeader($b))
            ->assertOk()
            ->assertJsonPath('tags', []);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/paperless/terms
    // -----------------------------------------------------------------------

    public function test_create_term_requires_device_token(): void
    {
        $this->postJson('/api/v1/paperless/terms', ['kind' => 'tag', 'name' => 'X'])
            ->assertUnauthorized();
    }

    public function test_create_term_validates_kind(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/paperless/terms', ['kind' => 'invalid', 'name' => 'X'], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kind']);
    }

    public function test_create_term_validates_name_required(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/paperless/terms', ['kind' => 'tag'], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_term_returns_422_when_paperless_not_configured(): void
    {
        $user = User::factory()->create();
        // No Paperless settings → client will be null.

        $this->postJson('/api/v1/paperless/terms', ['kind' => 'tag', 'name' => 'Invoices'], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);
    }

    public function test_create_term_calls_client_and_caches(): void
    {
        $user = User::factory()->create();
        $this->enablePaperless($user);

        // Mock the outbound HTTP call that PaperlessClient makes.
        Http::fake([
            'paperless.local/api/tags/' => Http::response(['id' => 99, 'name' => 'NewTag', 'colour' => '#aabbcc'], 201),
        ]);

        $this->postJson('/api/v1/paperless/terms', ['kind' => 'tag', 'name' => 'NewTag'], $this->authHeader($user))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('id', 99)
            ->assertJsonPath('name', 'NewTag');

        $this->assertDatabaseHas('paperless_terms', [
            'user_id' => $user->id,
            'kind' => 'tag',
            'paperless_id' => 99,
            'name' => 'NewTag',
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/paperless/documents
    // -----------------------------------------------------------------------

    public function test_submit_requires_device_token(): void
    {
        $this->postJson('/api/v1/paperless/documents')->assertUnauthorized();
    }

    public function test_submit_validates_file_required(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/paperless/documents', [], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_submit_validates_optional_fields(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/paperless/documents', [
            'file' => UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf'),
            'correspondent' => 'not-an-int',
            'document_type' => 'not-an-int',
        ], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correspondent', 'document_type']);
    }

    public function test_submit_returns_422_when_paperless_not_configured(): void
    {
        $user = User::factory()->create();
        // No Paperless settings.

        $this->postJson('/api/v1/paperless/documents', [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], $this->authHeader($user))
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);
    }

    public function test_submit_forwards_to_paperless_and_returns_task(): void
    {
        $user = User::factory()->create();
        $this->enablePaperless($user);

        Http::fake([
            'paperless.local/api/documents/post_document/' => Http::response('"task-uuid-abc"', 200),
        ]);

        $this->postJson('/api/v1/paperless/documents', [
            'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
            'title' => 'My Receipt',
        ], $this->authHeader($user))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('task', 'task-uuid-abc');
    }

    public function test_submit_response_has_no_store_header(): void
    {
        $user = User::factory()->create();
        $this->enablePaperless($user);

        Http::fake([
            'paperless.local/api/documents/post_document/' => Http::response('"task-uuid-xyz"', 200),
        ]);

        $response = $this->postJson('/api/v1/paperless/documents', [
            'file' => UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf'),
        ], $this->authHeader($user));

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/paperless/sync
    // -----------------------------------------------------------------------

    public function test_sync_requires_device_token(): void
    {
        $this->postJson('/api/v1/paperless/sync')->assertUnauthorized();
    }

    public function test_sync_returns_ok_false_when_not_configured(): void
    {
        $user = User::factory()->create();
        // PaperlessSync::run returns [] (empty) when not configured — no error thrown.

        $this->postJson('/api/v1/paperless/sync', [], $this->authHeader($user))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('counts', []);
    }

    public function test_sync_calls_service_and_returns_counts(): void
    {
        $user = User::factory()->create();
        $this->enablePaperless($user);

        // Stub out the live Paperless pagination calls made by PaperlessSync::run.
        Http::fake([
            'paperless.local/api/tags/*' => Http::response(['count' => 2, 'next' => null, 'results' => [
                ['id' => 1, 'name' => 'Tag1', 'colour' => null],
                ['id' => 2, 'name' => 'Tag2', 'colour' => null],
            ]], 200),
            'paperless.local/api/document_types/*' => Http::response(['count' => 0, 'next' => null, 'results' => []], 200),
            'paperless.local/api/correspondents/*' => Http::response(['count' => 1, 'next' => null, 'results' => [
                ['id' => 5, 'name' => 'Acme', 'colour' => null],
            ]], 200),
        ]);

        $this->postJson('/api/v1/paperless/sync', [], $this->authHeader($user))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('counts.tag', 2)
            ->assertJsonPath('counts.document_type', 0)
            ->assertJsonPath('counts.correspondent', 1);

        $this->assertDatabaseCount('paperless_terms', 3);
    }
}
