<?php

declare(strict_types=1);

namespace Tests\Feature\Paperless;

use App\Models\AppSettings;
use App\Models\PaperlessTerm;
use App\Services\Paperless\PaperlessSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperlessSyncTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        AppSettings::current()->update([
            'paperless_enabled' => true,
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => 'tok',
        ]);
    }

    public function test_it_is_a_no_op_when_disabled(): void
    {
        $counts = app(PaperlessSync::class)->run();
        $this->assertSame([], $counts);
        $this->assertSame(0, PaperlessTerm::count());
    }

    public function test_it_caches_terms_and_drops_stale_ones(): void
    {
        $this->configure();
        // A stale tag that no longer exists in Paperless must be pruned.
        PaperlessTerm::create(['kind' => 'tag', 'paperless_id' => 999, 'name' => 'Old']);

        Http::fake([
            '*/api/tags/*' => Http::response(['results' => [['id' => 1, 'name' => 'Invoice', 'color' => '#ff0000']], 'next' => null]),
            '*/api/document_types/*' => Http::response(['results' => [['id' => 5, 'name' => 'Contract']], 'next' => null]),
            '*/api/correspondents/*' => Http::response(['results' => [['id' => 7, 'name' => 'ACME'], ['id' => 8, 'name' => 'Bank']], 'next' => null]),
        ]);

        $counts = app(PaperlessSync::class)->run();

        $this->assertSame(['tag' => 1, 'document_type' => 1, 'correspondent' => 2], $counts);
        $this->assertDatabaseHas('paperless_terms', ['kind' => 'tag', 'paperless_id' => 1, 'name' => 'Invoice', 'color' => '#ff0000']);
        $this->assertDatabaseHas('paperless_terms', ['kind' => 'correspondent', 'paperless_id' => 8, 'name' => 'Bank']);
        $this->assertDatabaseMissing('paperless_terms', ['paperless_id' => 999]);
        $this->assertNotNull(AppSettings::current()->paperless_synced_at);
    }

    public function test_it_follows_pagination(): void
    {
        $this->configure();
        Http::fake([
            '*/api/tags/?page=1*' => Http::response(['results' => [['id' => 1, 'name' => 'A']], 'next' => 'https://p.example.com/api/tags/?page=2']),
            '*/api/tags/?page=2*' => Http::response(['results' => [['id' => 2, 'name' => 'B']], 'next' => null]),
            '*/api/document_types/*' => Http::response(['results' => [], 'next' => null]),
            '*/api/correspondents/*' => Http::response(['results' => [], 'next' => null]),
        ]);

        $counts = app(PaperlessSync::class)->run();

        $this->assertSame(2, $counts['tag']);
        $this->assertSame(2, PaperlessTerm::where('kind', 'tag')->count());
    }
}
