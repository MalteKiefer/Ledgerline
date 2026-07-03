<?php

declare(strict_types=1);

namespace Tests\Feature\Paperless;

use App\Models\AppSettings;
use App\Models\PaperlessTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperlessTransferTest extends TestCase
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

    public function test_terms_reports_unconfigured_when_disabled(): void
    {
        $this->signIn();

        $this->getJson(route('paperless.terms'))
            ->assertOk()
            ->assertJson(['configured' => false]);
    }

    public function test_terms_returns_the_cached_quick_picks(): void
    {
        $this->signIn();
        $this->configure();
        PaperlessTerm::create(['kind' => 'tag', 'paperless_id' => 1, 'name' => 'Invoice']);
        PaperlessTerm::create(['kind' => 'correspondent', 'paperless_id' => 2, 'name' => 'ACME']);

        $this->getJson(route('paperless.terms'))
            ->assertOk()
            ->assertJson([
                'configured' => true,
                'tags' => [['id' => 1, 'name' => 'Invoice']],
                'correspondents' => [['id' => 2, 'name' => 'ACME']],
            ]);
    }

    public function test_it_creates_a_term_and_caches_it(): void
    {
        $this->signIn();
        $this->configure();
        Http::fake(['*/api/tags/' => Http::response(['id' => 42, 'name' => 'Taxes'], 201)]);

        $this->postJson(route('paperless.terms.create'), ['kind' => 'tag', 'name' => 'Taxes'])
            ->assertOk()
            ->assertJson(['ok' => true, 'id' => 42, 'name' => 'Taxes']);

        $this->assertDatabaseHas('paperless_terms', ['kind' => 'tag', 'paperless_id' => 42, 'name' => 'Taxes']);
    }

    public function test_it_creates_a_correspondent_and_a_document_type(): void
    {
        $this->signIn();
        $this->configure();
        Http::fake([
            '*/api/correspondents/' => Http::response(['id' => 7, 'name' => 'ACME'], 201),
            '*/api/document_types/' => Http::response(['id' => 9, 'name' => 'Invoice'], 201),
        ]);

        $this->postJson(route('paperless.terms.create'), ['kind' => 'correspondent', 'name' => 'ACME'])
            ->assertOk()->assertJson(['ok' => true, 'id' => 7]);
        $this->postJson(route('paperless.terms.create'), ['kind' => 'document_type', 'name' => 'Invoice'])
            ->assertOk()->assertJson(['ok' => true, 'id' => 9]);

        $this->assertDatabaseHas('paperless_terms', ['kind' => 'correspondent', 'paperless_id' => 7]);
        $this->assertDatabaseHas('paperless_terms', ['kind' => 'document_type', 'paperless_id' => 9]);
    }

    public function test_creating_a_duplicate_term_reuses_the_existing_one(): void
    {
        $this->signIn();
        $this->configure();
        // Paperless answers 400 for a name that already exists; the client then
        // looks it up by name and returns it instead of failing.
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['name' => ['correspondent with this name already exists.']], 400);
            }

            return Http::response(['results' => [['id' => 3, 'name' => 'ACME']]], 200);
        });

        $this->postJson(route('paperless.terms.create'), ['kind' => 'correspondent', 'name' => 'ACME'])
            ->assertOk()->assertJson(['ok' => true, 'id' => 3, 'name' => 'ACME']);
    }

    public function test_it_uploads_a_document_with_metadata(): void
    {
        $this->signIn();
        $this->configure();
        Http::fake(['*/api/documents/post_document/' => Http::response('"task-uuid"', 200)]);

        $this->post(route('paperless.documents'), [
            'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
            'title' => 'March invoice',
            'created' => '2026-03-01',
            'correspondent' => 2,
            'document_type' => 5,
            'tags' => [1, 3],
        ])->assertOk()->assertJson(['ok' => true, 'task' => 'task-uuid']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/documents/post_document/'));
    }

    public function test_upload_is_rejected_when_not_configured(): void
    {
        $this->signIn();

        $this->post(route('paperless.documents'), [
            'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJson(['ok' => false]);
    }
}
