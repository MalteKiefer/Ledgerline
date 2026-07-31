<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proves the /api/v1 twin of every relational module is reachable with a real
 * Sanctum bearer token (abilities:device) the way the native app calls it:
 * it returns JSON (not a redirect), creates owner-stamped rows, lists them,
 * honours optimistic version -> 409, and is owner-scoped (user B cannot see or
 * mutate user A's rows). The api and web routes share the same controllers, so
 * this locks the contract the mobile clients depend on.
 */
class RelationalApiParityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device', ['device'])->plainTextToken];
    }

    /** Switch the acting bearer within a single test (Sanctum guard memoises per process). */
    private function reauth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_relational_api_requires_a_bearer(): void
    {
        $this->getJson('/api/v1/notes')->assertUnauthorized();
        $this->getJson('/api/v1/finance/data')->assertUnauthorized();
        $this->getJson('/api/v1/health/entries')->assertUnauthorized();
    }

    public function test_notes_api_create_list_and_version_conflict(): void
    {
        $u = User::factory()->create();

        $id = $this->postJson(route('api.notes.store'), ['title' => 'Api note', 'body' => 'b'], $this->bearer($u))
            ->assertCreated()->assertJsonPath('note.title', 'Api note')->json('note.id');

        $this->getJson(route('api.notes.index'), $this->bearer($u))->assertOk()->assertJsonCount(1, 'notes');

        $this->putJson(route('api.notes.update', $id), ['title' => 'v2', 'version' => 0], $this->bearer($u))
            ->assertOk()->assertJsonPath('note.version', 1);
        $this->putJson(route('api.notes.update', $id), ['title' => 'stale', 'version' => 0], $this->bearer($u))
            ->assertStatus(409)->assertJsonPath('error', 'version_conflict');
    }

    public function test_notes_api_is_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.notes.store'), ['title' => 'a'], $this->bearer($a))
            ->assertCreated()->json('note.id');

        $this->reauth();
        $this->getJson(route('api.notes.index'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'notes');
        $this->reauth();
        $this->putJson(route('api.notes.update', $id), ['title' => 'hijack'], $this->bearer($b))->assertNotFound();
        $this->reauth();
        $this->deleteJson(route('api.notes.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }

    public function test_todos_api_create_list_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.todos.store'), ['title' => 'Deploy'], $this->bearer($a))
            ->assertCreated()->assertJsonPath('todo.title', 'Deploy')->json('todo.id');
        $this->getJson(route('api.todos.index'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'todos');

        $this->reauth();
        $this->getJson(route('api.todos.index'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'todos');
        $this->reauth();
        $this->deleteJson(route('api.todos.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }

    public function test_bookmarks_api_create_list_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.bookmarks.store'), ['url' => 'https://example.com'], $this->bearer($a))
            ->assertCreated()->json('bookmark.id');
        $this->getJson(route('api.bookmarks.index'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'bookmarks');

        $this->reauth();
        $this->getJson(route('api.bookmarks.index'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'bookmarks');
        $this->reauth();
        $this->deleteJson(route('api.bookmarks.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }

    public function test_health_api_create_list_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.health.entries.store'),
            ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '80'], $this->bearer($a))
            ->assertCreated()->json('entry.id');
        $this->getJson(route('api.health.entries'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'entries');

        $this->reauth();
        $this->getJson(route('api.health.entries'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'entries');
        $this->reauth();
        $this->deleteJson(route('api.health.entries.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }

    public function test_explore_api_create_list_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $payload = [
            'name' => 'Api hike',
            'source_format' => 'recorded',
            'points' => [['lat' => 52.52, 'lng' => 13.4, 'ele' => 34, 't' => 1700000000]],
        ];
        $id = $this->postJson(route('api.explore.tracks.store'), $payload, $this->bearer($a))
            ->assertCreated()->assertJsonPath('track.source_format', 'recorded')->json('track.id');
        $this->getJson(route('api.explore.data'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'tracks');

        $this->reauth();
        $this->getJson(route('api.explore.data'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'tracks');
        $this->reauth();
        $this->putJson(route('api.explore.tracks.update', $id), ['name' => 'x'], $this->bearer($b))->assertNotFound();
    }

    public function test_finance_api_create_list_version_conflict_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.finance.partners.store'), ['name' => 'ACME GmbH'], $this->bearer($a))
            ->assertCreated()->assertJsonPath('partner.name', 'ACME GmbH')->json('partner.id');
        $this->getJson(route('api.finance.data'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'partners');

        $this->putJson(route('api.finance.partners.update', $id), ['name' => 'ACME AG', 'version' => 0], $this->bearer($a))
            ->assertOk()->assertJsonPath('partner.version', 1);
        $this->putJson(route('api.finance.partners.update', $id), ['name' => 'nope', 'version' => 0], $this->bearer($a))
            ->assertStatus(409);

        $this->reauth();
        $this->getJson(route('api.finance.data'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'partners');
        $this->reauth();
        $this->deleteJson(route('api.finance.partners.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }

    public function test_gallery_api_upload_data_and_owner_scoped_raw(): void
    {
        Storage::fake(config('files.disk'));
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = (int) $this->post(route('api.gallery.rel.upload'),
            ['file' => UploadedFile::fake()->image('p.jpg', 400, 300)], $this->bearer($a))
            ->assertCreated()->json('photo.id');

        $this->getJson(route('api.gallery.rel.data'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'photos');
        $this->get(route('api.gallery.rel.raw', $id), $this->bearer($a))->assertOk();

        $this->reauth();
        $this->getJson(route('api.gallery.rel.data'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'photos');
        $this->reauth();
        $this->get(route('api.gallery.rel.raw', $id), $this->bearer($b))->assertNotFound();
    }
}
