<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Modules\Finance\Application\Commands\PublishDocumentRevision;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\BladeDocumentRenderer;
use App\Modules\Finance\Infrastructure\Pdf\FlysystemDocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\QuotePdfViewModel;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_document_ports_are_bound_to_quote_pdf_adapters(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');

        $this->assertInstanceOf(BladeDocumentRenderer::class, app(DocumentRenderer::class));
        $this->assertInstanceOf(FlysystemDocumentStorage::class, app(DocumentStorage::class));
    }

    public function test_production_publication_is_byte_verified_immutable_and_idempotent(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');
        [$owner, $revisionId] = $this->draftRevision();
        $this->actingAs($owner);
        $command = app(PublishDocumentRevision::class);

        $published = $command->handle(new DocumentRevisionId($revisionId));
        $retry = $command->handle(new DocumentRevisionId($revisionId));
        $bytes = Storage::disk('quote-pdfs')->get($published->path);

        $this->assertEquals($published, $retry);
        $this->assertIsString($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(hash('sha256', $bytes), $published->sha256);
        $this->assertMatchesRegularExpression(
            '#\Afinance/revisions/[a-f0-9]{2}/[a-f0-9]{64}\.pdf\z#',
            $published->path,
        );
        $this->assertCount(1, Storage::disk('quote-pdfs')->allFiles());
    }

    public function test_production_publication_compensates_the_owned_object_after_database_failure(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');
        [$owner, $revisionId] = $this->draftRevision('018f4ca3-224d-7d8d-9f00-949494949494');
        $this->actingAs($owner);
        DocumentActivityRecord::creating(static function (DocumentActivityRecord $activity): void {
            if ($activity->type === 'revision.published') {
                throw new RuntimeException('Injected activity failure.');
            }
        });

        try {
            app(PublishDocumentRevision::class)->handle(new DocumentRevisionId($revisionId));
            $this->fail('Publication unexpectedly survived the database failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected activity failure.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('quote-pdfs')->allFiles());
        $this->assertDatabaseHas('finance_document_revisions', [
            'id' => $revisionId,
            'status' => 'draft',
            'pdf_path' => null,
            'published_at' => null,
        ]);
    }

    public function test_owner_can_stream_an_immutable_revision_inline_or_as_a_download(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');
        [$owner, $quoteUuid, $revisionId, $path, $bytes] = $this->publishedQuote();
        $token = $owner->createToken('device', ['device'])->plainTextToken;

        $inline = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [
                'quote' => $quoteUuid,
                'revision' => $revisionId,
            ]));

        $inline->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('ETag', '"'.hash('sha256', $bytes).'"');
        $this->assertStringContainsString('private', (string) $inline->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $inline->headers->get('Cache-Control'));
        $this->assertSame('inline; filename=AN-2026-0042-R2.pdf', $inline->headers->get('Content-Disposition'));
        $this->assertSame($bytes, $inline->streamedContent());

        $download = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [
                'quote' => $quoteUuid,
                'revision' => $revisionId,
                'download' => 1,
            ]));

        $download->assertOk();
        $this->assertSame('attachment; filename=AN-2026-0042-R2.pdf', $download->headers->get('Content-Disposition'));
        Storage::disk('quote-pdfs')->assertExists($path);
    }

    public function test_pdf_stream_hides_foreign_deleted_mismatched_and_untrusted_objects_as_not_found(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');
        [$owner, $quoteUuid, $revisionId] = $this->publishedQuote();
        $otherOwner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $otherToken = $otherOwner->createToken('device', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [$quoteUuid, $revisionId]))
            ->assertNotFound();

        $ownerToken = $owner->createToken('device-2', ['device'])->plainTextToken;
        DB::table('finance_quote_series')->where('user_id', $owner->id)->update(['deleted_at' => now()]);
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [$quoteUuid, $revisionId]))
            ->assertNotFound();

        DB::table('finance_quote_series')->where('user_id', $owner->id)->update(['deleted_at' => null]);
        [, , $otherRevisionId] = $this->publishedQuote(
            $owner,
            '018f4ca3-224d-7d8d-9f00-929292929292',
            'AN-2026-0043',
        );
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [$quoteUuid, $otherRevisionId]))
            ->assertNotFound();

        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_path' => '../outside.pdf',
        ]);
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->get(route('api.finance-v2.quotes.revisions.pdf', [$quoteUuid, $revisionId]))
            ->assertNotFound();
    }

    public function test_pdf_stream_rejects_missing_or_digest_mismatched_immutable_bytes(): void
    {
        Storage::fake('quote-pdfs');
        config()->set('files.disk', 'quote-pdfs');
        [$owner, $quoteUuid, $revisionId, $path] = $this->publishedQuote();
        $token = $owner->createToken('device', ['device'])->plainTextToken;
        $url = route('api.finance-v2.quotes.revisions.pdf', [$quoteUuid, $revisionId]);

        Storage::disk('quote-pdfs')->delete($path);
        $this->withHeader('Authorization', 'Bearer '.$token)->get($url)->assertNotFound();

        Storage::disk('quote-pdfs')->put($path, '%PDF-corrupted');
        $this->withHeader('Authorization', 'Bearer '.$token)->get($url)->assertNotFound();

        Storage::disk('quote-pdfs')->put($path, '<script>not a PDF</script>');
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_sha256' => hash('sha256', '<script>not a PDF</script>'),
        ]);
        $this->withHeader('Authorization', 'Bearer '.$token)->get($url)->assertNotFound();
    }

    public function test_storage_uses_the_capability_path_preserves_bytes_and_never_overwrites(): void
    {
        Storage::fake('quote-pdfs');
        $disk = Storage::disk('quote-pdfs');
        $storage = new FlysystemDocumentStorage($disk);
        $token = str_repeat('ab', 32);
        $path = "finance/revisions/ab/{$token}.pdf";

        $stored = $storage->putPdf('018f4ca3-224d-7d8d-9f00-101010101010', '%PDF-first', $token);

        $this->assertSame($path, $stored->path);
        $this->assertSame(hash('sha256', '%PDF-first'), $stored->sha256);
        $disk->assertExists($path);
        $this->assertSame('%PDF-first', $disk->get($path));

        $otherToken = str_repeat('ac', 32);
        $other = $storage->putPdf(
            '018f4ca3-224d-7d8d-9f00-101010101010',
            '%PDF-first',
            $otherToken,
        );
        $this->assertNotSame($stored->path, $other->path);
        $disk->assertExists($other->path);

        $this->expectException(LogicException::class);
        $storage->putPdf('018f4ca3-224d-7d8d-9f00-101010101010', '%PDF-second', $token);
    }

    public function test_storage_cleanup_derives_only_the_supplied_capability_path(): void
    {
        Storage::fake('quote-pdfs');
        $disk = Storage::disk('quote-pdfs');
        $storage = new FlysystemDocumentStorage($disk);
        $ownedToken = str_repeat('cd', 32);
        $otherToken = str_repeat('ef', 32);
        $ownedPath = $storage->putPdf('series', '%PDF-owned', $ownedToken)->path;
        $otherPath = $storage->putPdf('series', '%PDF-other', $otherToken)->path;

        $storage->delete('../'.$otherToken);
        $storage->delete($ownedToken);

        $disk->assertMissing($ownedPath);
        $disk->assertExists($otherPath);
    }

    public function test_storage_failure_is_reported_and_the_same_capability_can_be_retried_after_cleanup(): void
    {
        Storage::fake('quote-pdfs');
        $disk = Storage::disk('quote-pdfs');
        $storage = new FlysystemDocumentStorage($disk);
        $token = str_repeat('12', 32);

        $storage->putPdf('series', '%PDF-original', $token);

        try {
            $storage->putPdf('series', '%PDF-retry', $token);
            $this->fail('A colliding storage write unexpectedly succeeded.');
        } catch (LogicException $exception) {
            $this->assertSame('The document capability is already in use.', $exception->getMessage());
        }

        $storage->delete($token);
        $stored = $storage->putPdf('series', '%PDF-retry', $token);
        $this->assertSame("finance/revisions/12/{$token}.pdf", $stored->path);
        $this->assertSame(hash('sha256', '%PDF-retry'), $stored->sha256);
        $this->assertSame('%PDF-retry', $disk->get($stored->path));
    }

    public function test_storage_reports_a_failed_private_write(): void
    {
        Storage::fake('quote-pdfs');
        $disk = Storage::disk('quote-pdfs');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $storage = new FlysystemDocumentStorage(new RejectingPutFilesystem($disk));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The PDF could not be stored.');
        $storage->putPdf('series', '%PDF-failed', str_repeat('34', 32));
    }

    public function test_storage_rejects_non_canonical_or_traversal_capabilities(): void
    {
        Storage::fake('quote-pdfs');
        $storage = new FlysystemDocumentStorage(Storage::disk('quote-pdfs'));

        $this->expectException(InvalidArgumentException::class);
        $storage->putPdf('series', '%PDF-invalid', '../'.str_repeat('a', 64));
    }

    public function test_storage_rejects_non_pdf_bytes_before_writing(): void
    {
        Storage::fake('quote-pdfs');
        $disk = Storage::disk('quote-pdfs');
        $storage = new FlysystemDocumentStorage($disk);

        $this->expectException(InvalidArgumentException::class);
        try {
            $storage->putPdf('series', '<html>not a PDF</html>', str_repeat('56', 32));
        } finally {
            $this->assertSame([], $disk->allFiles());
        }
    }

    public function test_renderer_emits_deterministic_pdf_bytes_for_the_same_canonical_snapshot(): void
    {
        $renderer = new BladeDocumentRenderer(app('view'));

        $first = $renderer->render($this->snapshot());
        $second = $renderer->render($this->snapshot());

        $this->assertStringStartsWith('%PDF-', $first);
        $this->assertSame($first, $second);
        $this->assertSame(hash('sha256', $first), hash('sha256', $second));
    }

    public function test_renderer_refuses_non_quote_snapshots(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['document_type'] = 'invoice';

        $this->expectException(InvalidArgumentException::class);
        (new BladeDocumentRenderer(app('view')))->render($snapshot);
    }

    public function test_quote_pdf_view_escapes_customer_content_and_displays_authoritative_fields(): void
    {
        $viewModel = QuotePdfViewModel::fromSnapshot($this->snapshot());

        $html = view('finance.quotes.pdf', $viewModel->viewData())->render();

        $this->assertStringContainsString('AN-2026-0042', $html);
        $this->assertStringContainsString('AN-2026-0042-R2', $html);
        $this->assertStringContainsString('28.08.2026', $html);
        $this->assertStringContainsString('27.09.2026', $html);
        $this->assertStringContainsString('19,00', $html);
        $this->assertStringContainsString('Netto nach Rabatt', $html);
        $this->assertStringContainsString('267,75', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('payment-qr', $html);
        $this->assertStringNotContainsString('Internal only', $html);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'schema_version' => 1,
            'document_type' => 'quote',
            'series_uuid' => '018f4ca3-224d-7d8d-9f00-101010101010',
            'document_number' => 'AN-2026-0042',
            'revision_number' => 2,
            'revision_label' => 'AN-2026-0042-R2',
            'title' => 'Network refresh',
            'customer' => [
                'name' => '<script>alert("x")</script>',
                'email' => 'billing@example.com',
                'street' => 'Main Street 1',
                'postal_code' => '10115',
                'city' => 'Berlin',
            ],
            'partner_id' => null,
            'issue_date' => '2026-08-28',
            'valid_until' => '2026-09-27',
            'currency' => 'EUR',
            'lines' => [[
                'description' => 'Consulting',
                'quantity' => '2.5000',
                'quantity_scaled' => 25_000,
                'unit' => 'hour',
                'unit_price' => '100.00',
                'unit_price_minor' => 10_000,
                'currency' => 'EUR',
                'tax_rate' => '19.00',
                'tax_rate_basis_points' => 1900,
                'kind' => 'service',
                'product_id' => null,
            ]],
            'discount' => [
                'type' => 'percent',
                'value' => '10.00',
                'basis_points' => 1000,
                'currency' => 'EUR',
            ],
            'totals' => [
                'net_minor' => 22_500,
                'vat_minor' => 4_275,
                'gross_minor' => 26_775,
                'discount_minor' => 2_500,
                'currency' => 'EUR',
                'tax_breakdowns' => [[
                    'tax_rate_basis_points' => 1900,
                    'net_minor' => 22_500,
                    'vat_minor' => 4_275,
                    'gross_minor' => 26_775,
                ]],
            ],
            'intro_text' => 'Intro',
            'outro_text' => 'Outro',
            'customer_note' => 'Customer note',
        ];
    }

    /** @return array{User, string, int, string, string} */
    private function publishedQuote(
        ?User $owner = null,
        string $quoteUuid = '018f4ca3-224d-7d8d-9f00-919191919191',
        string $number = 'AN-2026-0042',
    ): array {
        $owner ??= User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $bytes = '%PDF-immutable-quote';
        $ownershipToken = hash('sha256', $quoteUuid);
        $path = sprintf(
            'finance/revisions/%s/%s.pdf',
            substr($ownershipToken, 0, 2),
            $ownershipToken,
        );
        $now = now();

        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $quoteUuid,
            'document_type' => 'quote',
            'status' => 'sent',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_quote_series')->insert([
            'document_series_id' => $seriesId,
            'user_id' => $owner->id,
            'document_type' => 'quote',
            'partner_id' => null,
            'current_revision_id' => null,
            'number' => $number,
            'sequence_year' => 2026,
            'sequence_number' => $number === 'AN-2026-0042' ? 42 : 43,
            'version' => 1,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 2,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode($this->snapshot(), JSON_THROW_ON_ERROR),
            'net_minor' => 22_500,
            'vat_minor' => 4_275,
            'gross_minor' => 26_775,
            'currency' => 'EUR',
            'pdf_path' => $path,
            'pdf_sha256' => hash('sha256', $bytes),
            'published_at' => $now,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
        ]);
        Storage::disk('quote-pdfs')->put($path, $bytes);

        $this->assertTrue(Route::has('api.finance-v2.health'));

        return [$owner, $quoteUuid, $revisionId, $path, $bytes];
    }

    /** @return array{User, int} */
    private function draftRevision(
        string $quoteUuid = '018f4ca3-224d-7d8d-9f00-939393939393',
    ): array {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $now = now();
        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $quoteUuid,
            'document_type' => 'quote',
            'status' => 'draft',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'draft',
            'snapshot' => json_encode($this->snapshot(), JSON_THROW_ON_ERROR),
            'net_minor' => 22_500,
            'vat_minor' => 4_275,
            'gross_minor' => 26_775,
            'currency' => 'EUR',
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => null,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);

        return [$owner, $revisionId];
    }
}

final class RejectingPutFilesystem extends FilesystemAdapter
{
    public function __construct(FilesystemAdapter $delegate)
    {
        parent::__construct($delegate->getDriver(), $delegate->getAdapter());
    }

    public function put($path, $contents, $options = []): bool
    {
        return false;
    }
}
