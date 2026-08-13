<?php

declare(strict_types=1);

namespace Tests\Feature\Paperless;

use App\Models\PaperlessTerm;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Paperless\PaperlessSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperlessSyncTest extends TestCase
{
    use RefreshDatabase;

    private function configure(int $userId): void
    {
        UserSetting::for($userId)->update([
            'paperless_enabled' => true,
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => 'tok',
        ]);
    }

    public function test_it_is_a_no_op_when_disabled(): void
    {
        $user = User::factory()->create();
        $counts = app(PaperlessSync::class)->run($user->id);
        $this->assertSame([], $counts);
        $this->assertSame(0, PaperlessTerm::withoutGlobalScopes()->count());
    }

    public function test_it_caches_terms_and_drops_stale_ones(): void
    {
        $user = User::factory()->create();
        $this->configure($user->id);
        // A stale tag that no longer exists in Paperless must be pruned.
        PaperlessTerm::create(['user_id' => $user->id, 'kind' => 'tag', 'paperless_id' => 999, 'name' => 'Old']);

        Http::fake([
            '*/api/tags/*' => Http::response(['results' => [['id' => 1, 'name' => 'Invoice', 'color' => '#ff0000']], 'next' => null]),
            '*/api/document_types/*' => Http::response(['results' => [['id' => 5, 'name' => 'Contract']], 'next' => null]),
            '*/api/correspondents/*' => Http::response(['results' => [['id' => 7, 'name' => 'ACME'], ['id' => 8, 'name' => 'Bank']], 'next' => null]),
        ]);

        $counts = app(PaperlessSync::class)->run($user->id);

        $this->assertSame(['tag' => 1, 'document_type' => 1, 'correspondent' => 2], $counts);
        $this->assertDatabaseHas('paperless_terms', ['user_id' => $user->id, 'kind' => 'tag', 'paperless_id' => 1, 'name' => 'Invoice', 'color' => '#ff0000']);
        $this->assertDatabaseHas('paperless_terms', ['user_id' => $user->id, 'kind' => 'correspondent', 'paperless_id' => 8, 'name' => 'Bank']);
        $this->assertDatabaseMissing('paperless_terms', ['paperless_id' => 999]);
        $this->assertNotNull(UserSetting::for($user->id)->paperless_synced_at);
    }

    public function test_it_follows_pagination(): void
    {
        $user = User::factory()->create();
        $this->configure($user->id);
        Http::fake([
            '*/api/tags/?page=1*' => Http::response(['results' => [['id' => 1, 'name' => 'A']], 'next' => 'https://p.example.com/api/tags/?page=2']),
            '*/api/tags/?page=2*' => Http::response(['results' => [['id' => 2, 'name' => 'B']], 'next' => null]),
            '*/api/document_types/*' => Http::response(['results' => [], 'next' => null]),
            '*/api/correspondents/*' => Http::response(['results' => [], 'next' => null]),
        ]);

        $counts = app(PaperlessSync::class)->run($user->id);

        $this->assertSame(2, $counts['tag']);
        $this->assertSame(2, PaperlessTerm::withoutGlobalScopes()->where('kind', 'tag')->count());
    }
}
