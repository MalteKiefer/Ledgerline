<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Http\Resources\InvoiceRevisionResource;
use App\Modules\Finance\Infrastructure\Inventory\LegacyStockLedgerAdapter;
use App\Modules\Finance\Infrastructure\Pdf\BladeDocumentRenderer;
use App\Modules\Finance\Infrastructure\Pdf\FlysystemDocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\InvoicePdfViewModel;
use App\Modules\Finance\Infrastructure\Pdf\LocalAtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Pdf\S3AtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\LockedInvoiceNumberAllocator;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\OrphanDocumentReconciler;
use App\Modules\Finance\Infrastructure\SystemClock;
use App\Support\BinaryProcess;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_emits_deterministic_pdf_bytes_from_only_the_canonical_invoice_snapshot(): void
    {
        $renderer = new BladeDocumentRenderer(app('view'));
        $snapshot = $this->snapshot();

        $first = $renderer->render($snapshot);
        $second = $renderer->render($snapshot);

        $this->assertStringStartsWith('%PDF-', $first);
        $this->assertSame($first, $second);
        $this->assertSame(hash('sha256', $first), hash('sha256', $second));
    }

    public function test_rendered_pdf_text_contains_the_authoritative_number_customer_and_totals_when_poppler_is_available(): void
    {
        if (! BinaryProcess::available('pdftotext')) {
            $this->markTestSkipped('pdftotext is unavailable.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ledgerline-invoice-pdf-');
        $this->assertIsString($path);

        try {
            file_put_contents($path, (new BladeDocumentRenderer(app('view')))->render($this->snapshot()));
            $text = BinaryProcess::run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);

            $this->assertIsString($text);
            $this->assertStringContainsString('RE-2026-0042', $text);
            $this->assertStringContainsString('<script>alert("x")</script>', $text);
            $this->assertStringContainsString('291,83 EUR', $text);
        } finally {
            @unlink($path);
        }
    }

    public function test_invoice_pdf_view_escapes_customer_content_and_displays_authoritative_values(): void
    {
        $viewModel = InvoicePdfViewModel::fromSnapshot($this->snapshot());

        $html = view('finance.invoices.pdf', $viewModel->viewData())->render();

        $this->assertStringContainsString('RE-2026-0042', $html);
        $this->assertStringContainsString('28.08.2026', $html);
        $this->assertStringContainsString('11.09.2026', $html);
        $this->assertStringContainsString('19,00', $html);
        $this->assertStringContainsString('7,00', $html);
        $this->assertStringContainsString('291,83 EUR', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('Internal only', $html);
        $this->assertStringNotContainsString('https://attacker.example', $html);
    }

    public function test_renderer_rejects_noncanonical_schema_versions_and_recalculated_total_mismatches(): void
    {
        $renderer = new BladeDocumentRenderer(app('view'));
        $unsupported = $this->snapshot();
        $unsupported['schema_version'] = 2;

        try {
            $renderer->render($unsupported);
            $this->fail('An unsupported invoice snapshot schema was rendered.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('The invoice PDF snapshot schema is unsupported.', $exception->getMessage());
        }

        $mismatched = $this->snapshot();
        $mismatched['totals']['gross_minor']++;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice PDF totals do not match the canonical line calculation.');
        $renderer->render($mismatched);
    }

    public function test_production_invoice_finalization_stores_one_digest_verified_immutable_pdf_on_replay(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'company_name' => 'Ledgerline GmbH',
            'company_address' => "Main Street 1\n10115 Berlin",
            'company_email' => 'billing@example.test',
            'invoice_number_format' => 'RE-YYYY-NNNN',
            'invoice_next_number' => 42,
        ]);
        $repository = $this->invoiceRepository();
        $invoiceId = $repository->createDraft($this->invoiceDraft());
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        $command = $this->finalizeCommand($repository, $storage);
        $key = new IdempotencyKey('invoice-pdf-production-replay-1');

        try {
            $first = $command->handle($invoiceId, $key);
            $second = $command->handle($invoiceId, $key);

            $this->assertSame($first->revisionId, $second->revisionId);
            $this->assertSame($first->pdfPath, $second->pdfPath);
            $this->assertSame($first->pdfSha256, $second->pdfSha256);
            $absolutePath = $this->objectPath($root, $first->pdfPath);
            $this->assertFileExists($absolutePath);
            $bytes = file_get_contents($absolutePath);
            $this->assertIsString($bytes);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertSame(hash('sha256', $bytes), $first->pdfSha256);
            $this->assertCount(1, glob($root.DIRECTORY_SEPARATOR.'finance'.DIRECTORY_SEPARATOR
                .'revisions'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*.pdf') ?: []);
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_production_invoice_finalization_compensates_owned_pdf_when_database_publication_fails(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $repository = $this->invoiceRepository();
        $invoiceId = $repository->createDraft($this->invoiceDraft());
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER invoice_pdf_publication_failure
            BEFORE INSERT ON finance_document_activities
            WHEN NEW.type = 'invoice.finalized'
            BEGIN
                SELECT RAISE(ABORT, 'invoice_pdf_publication_failure');
            END
            SQL);

        try {
            $this->expectException(\Throwable::class);
            $this->finalizeCommand($repository, $storage)->handle(
                $invoiceId,
                new IdempotencyKey('invoice-pdf-production-compensation-1'),
            );
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS invoice_pdf_publication_failure');
            $this->assertSame([], glob($root.DIRECTORY_SEPARATOR.'finance'.DIRECTORY_SEPARATOR
                .'revisions'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*.pdf') ?: []);
            $this->assertDatabaseHas('finance_invoices', [
                'id' => $invoiceId->value,
                'workflow_status' => 'draft',
                'number' => null,
            ]);
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_owner_can_stream_an_immutable_invoice_revision_inline_or_as_a_download(): void
    {
        Storage::fake('invoice-pdfs');
        config()->set('files.disk', 'invoice-pdfs');
        [$owner, $invoiceUuid, $revisionId, $path, $bytes] = $this->publishedInvoice();
        $token = $owner->createToken('device', ['device'])->plainTextToken;

        $inline = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get(route('api.finance-v2.invoices.revisions.pdf', [
                'invoice' => $invoiceUuid,
                'revision' => $revisionId,
            ]));

        $inline->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('ETag', '"'.hash('sha256', $bytes).'"');
        $this->assertStringContainsString('private', (string) $inline->headers->get('Cache-Control'));
        $this->assertSame($bytes, $inline->streamedContent());

        $download = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get(route('api.finance-v2.invoices.revisions.pdf', [
                'invoice' => $invoiceUuid,
                'revision' => $revisionId,
                'download' => 1,
            ]));
        $download->assertOk();
        $this->assertSame(
            'attachment; filename=RE-2026-0042-R1.pdf',
            $download->headers->get('Content-Disposition'),
        );
        Storage::disk('invoice-pdfs')->assertExists($path);
    }

    public function test_pdf_stream_hides_foreign_series_guesses_and_untrusted_objects_as_not_found(): void
    {
        Storage::fake('invoice-pdfs');
        config()->set('files.disk', 'invoice-pdfs');
        [$owner, $invoiceUuid, $revisionId, $path] = $this->publishedInvoice();
        $otherOwner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $otherToken = $otherOwner->createToken('device', ['device'])->plainTextToken;
        $url = route('api.finance-v2.invoices.revisions.pdf', [$invoiceUuid, $revisionId]);

        $this->withHeader('Authorization', 'Bearer '.$otherToken)->get($url)->assertNotFound();

        $ownerToken = $owner->createToken('device-2', ['device'])->plainTextToken;
        [, , $otherRevisionId] = $this->publishedInvoice(
            $owner,
            '018f4ca3-224d-7d8d-9f00-929292929292',
            'RE-2026-0043',
        );
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->get(route('api.finance-v2.invoices.revisions.pdf', [$invoiceUuid, $otherRevisionId]))
            ->assertNotFound();

        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_path' => '../outside.pdf',
        ]);
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)->get($url)->assertNotFound();

        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_path' => $path,
        ]);
        Storage::disk('invoice-pdfs')->put($path, '%PDF-corrupted');
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)->get($url)->assertNotFound();

        Storage::disk('invoice-pdfs')->put($path, '<script>not a PDF</script>');
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_sha256' => hash('sha256', '<script>not a PDF</script>'),
        ]);
        $this->withHeader('Authorization', 'Bearer '.$ownerToken)->get($url)->assertNotFound();
    }

    public function test_invoice_pdf_stream_rejects_non_positive_and_oversized_revision_route_values_as_not_found(): void
    {
        Storage::fake('invoice-pdfs');
        config()->set('files.disk', 'invoice-pdfs');
        [$owner, $invoiceUuid] = $this->publishedInvoice();
        $token = $owner->createToken('device', ['device'])->plainTextToken;

        foreach (['0', '00', '9223372036854775808', str_repeat('9', 100)] as $revision) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->get(route('api.finance-v2.invoices.revisions.pdf', [$invoiceUuid, $revision]))
                ->assertNotFound();
        }
    }

    public function test_invoice_revision_resource_exposes_verified_metadata_without_the_private_path(): void
    {
        Storage::fake('invoice-pdfs');
        config()->set('files.disk', 'invoice-pdfs');
        [, $invoiceUuid, $revisionId] = $this->publishedInvoice();
        $revision = DocumentRevisionRecord::query()->withoutGlobalScopes()->findOrFail($revisionId);

        $resource = (new InvoiceRevisionResource($revision, $invoiceUuid))->resolve();

        $this->assertSame($revisionId, $resource['id']);
        $this->assertSame(1, $resource['revision_number']);
        $this->assertSame('published', $resource['status']);
        $this->assertSame(hash('sha256', '%PDF-immutable-invoice'), $resource['pdf_sha256']);
        $this->assertSame(
            route('api.finance-v2.invoices.revisions.pdf', [$invoiceUuid, $revisionId]),
            $resource['pdf_url'],
        );
        $this->assertArrayNotHasKey('pdf_path', $resource);
    }

    public function test_orphan_reconciler_removes_only_aged_unreferenced_generation_owned_documents(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $objects = new LocalAtomicDocumentObjectStore($root, $locks);
        $storage = new FlysystemDocumentStorage($objects);
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $oldOrphan = $this->storageWrite(str_repeat('11', 32), str_repeat('12', 32), '%PDF-old-orphan');
        $referenced = $this->storageWrite(str_repeat('21', 32), str_repeat('22', 32), '%PDF-referenced');
        $youngOrphan = $this->storageWrite(str_repeat('31', 32), str_repeat('32', 32), '%PDF-young');
        $untrusted = $this->storageWrite(str_repeat('41', 32), str_repeat('42', 32), '%PDF-untrusted');

        try {
            $oldPath = $storage->putPdf('series', '%PDF-old-orphan', $oldOrphan)->path;
            $referencedPath = $storage->putPdf('series', '%PDF-referenced', $referenced)->path;
            $youngPath = $storage->putPdf('series', '%PDF-young', $youngOrphan)->path;
            $untrustedPath = $storage->putPdf('series', '%PDF-untrusted', $untrusted)->path;
            foreach ([$oldPath, $referencedPath, $untrustedPath] as $path) {
                touch($this->objectPath($root, $path), $now->getTimestamp() - 172_800);
            }
            touch($this->objectPath($root, $youngPath), $now->getTimestamp() - 60);
            file_put_contents(
                $this->objectPath($root, $untrustedPath).'.ledgerline-owner',
                '{"cleanup_proof":"tampered"}',
            );
            $this->referenceDocumentPath($referencedPath, hash('sha256', '%PDF-referenced'));

            $deleted = (new OrphanDocumentReconciler($objects, 86_400))->reconcile($now);

            $this->assertSame(1, $deleted);
            $this->assertFileDoesNotExist($this->objectPath($root, $oldPath));
            $this->assertFileExists($this->objectPath($root, $referencedPath));
            $this->assertFileExists($this->objectPath($root, $youngPath));
            $this->assertFileExists($this->objectPath($root, $untrustedPath));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_local_atomic_delete_reports_only_an_actual_generation_owned_deletion(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $objects = new LocalAtomicDocumentObjectStore($root, $locks);
        $bytes = '%PDF-local-delete-result';
        $owned = $this->storageWrite(str_repeat('91', 32), str_repeat('92', 32), $bytes);
        $stale = $this->storageWrite($owned->ownershipToken, str_repeat('93', 32), $bytes);
        $path = 'finance/revisions/91/'.$owned->ownershipToken.'.pdf';

        try {
            $objects->create($path, $bytes, $owned);

            $this->assertFalse($objects->deleteIfOwned($path, $stale));
            $this->assertFileExists($this->objectPath($root, $path));
            $this->assertTrue($objects->deleteIfOwned($path, $owned));
            $this->assertFileDoesNotExist($this->objectPath($root, $path));
            $this->assertFalse($objects->deleteIfOwned($path, $owned));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_s3_orphan_reconciliation_lists_only_the_finance_prefix_and_conditionally_deletes_owned_bytes(): void
    {
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $bytes = '%PDF-s3-orphan';
        $write = $this->storageWrite(str_repeat('51', 32), str_repeat('52', 32), $bytes);
        $path = 'finance/revisions/51/'.str_repeat('51', 32).'.pdf';
        $key = 'private-prefix/'.$path;
        $lastModified = $now->modify('-2 days');
        $commands = new InvoiceS3CommandLog;
        $metadata = [
            'ledgerline-proof' => $write->cleanupProof,
            'ledgerline-sha256' => $write->sha256,
            'ledgerline-generation' => $write->generation(),
        ];
        $handler = new MockHandler([
            new Result([
                'Contents' => [['Key' => $key, 'LastModified' => $lastModified]],
                'IsTruncated' => false,
            ]),
            new Result(['Metadata' => $metadata]),
            new Result([
                'ETag' => '"orphan-etag"',
                'LastModified' => $lastModified,
                'ContentLength' => strlen($bytes),
                'Metadata' => $metadata,
            ]),
            static function (CommandInterface $command) use ($commands): Result {
                $commands->record($command);

                return new Result;
            },
        ]);
        $objects = new S3AtomicDocumentObjectStore(
            $this->s3Client($handler),
            'private-bucket',
            'private-prefix',
        );

        $deleted = (new OrphanDocumentReconciler($objects, 86_400))->reconcile($now);

        $this->assertSame(1, $deleted);
        $this->assertCount(0, $handler);
        $delete = $commands->get(0);
        $this->assertSame('DeleteObject', $delete->getName());
        $this->assertSame($key, $delete['Key']);
        $this->assertSame('"orphan-etag"', $delete['IfMatch']);
        $this->assertSame($lastModified, $delete['IfMatchLastModifiedTime']);
        $this->assertSame(strlen($bytes), $delete['IfMatchSize']);
    }

    public function test_s3_orphan_reconciliation_rejects_a_capability_stored_below_the_wrong_shard(): void
    {
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $token = str_repeat('61', 32);
        $handler = new MockHandler([
            new Result([
                'Contents' => [[
                    'Key' => 'private-prefix/finance/revisions/ff/'.$token.'.pdf',
                    'LastModified' => $now->modify('-2 days'),
                ]],
                'IsTruncated' => false,
            ]),
        ]);
        $objects = new S3AtomicDocumentObjectStore(
            $this->s3Client($handler),
            'private-bucket',
            'private-prefix',
        );

        $this->assertSame(0, (new OrphanDocumentReconciler($objects, 86_400))->reconcile($now));
        $this->assertCount(0, $handler);
    }

    public function test_s3_orphan_reconciliation_does_not_count_a_generation_changed_before_delete(): void
    {
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $bytes = '%PDF-s3-generation-race';
        $stale = $this->storageWrite(str_repeat('71', 32), str_repeat('72', 32), $bytes);
        $current = $this->storageWrite($stale->ownershipToken, str_repeat('73', 32), $bytes);
        $key = 'private-prefix/finance/revisions/71/'.$stale->ownershipToken.'.pdf';
        $handler = new MockHandler([
            new Result([
                'Contents' => [['Key' => $key, 'LastModified' => $now->modify('-2 days')]],
                'IsTruncated' => false,
            ]),
            new Result(['Metadata' => $this->s3Metadata($stale)]),
            new Result([
                'ETag' => '"current"',
                'LastModified' => $now->modify('-1 minute'),
                'ContentLength' => strlen($bytes),
                'Metadata' => $this->s3Metadata($current),
            ]),
        ]);
        $objects = new S3AtomicDocumentObjectStore(
            $this->s3Client($handler),
            'private-bucket',
            'private-prefix',
        );

        $this->assertSame(0, (new OrphanDocumentReconciler($objects, 86_400))->reconcile($now));
        $this->assertCount(0, $handler);
    }

    public function test_s3_orphan_reconciliation_does_not_count_a_conditional_delete_conflict(): void
    {
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $bytes = '%PDF-s3-delete-conflict';
        $write = $this->storageWrite(str_repeat('81', 32), str_repeat('82', 32), $bytes);
        $key = 'private-prefix/finance/revisions/81/'.$write->ownershipToken.'.pdf';
        $handler = new MockHandler;
        $client = $this->s3Client($handler);
        $handler->append(
            new Result([
                'Contents' => [['Key' => $key, 'LastModified' => $now->modify('-2 days')]],
                'IsTruncated' => false,
            ]),
            new Result(['Metadata' => $this->s3Metadata($write)]),
            new Result([
                'ETag' => '"owned"',
                'LastModified' => $now->modify('-2 days'),
                'ContentLength' => strlen($bytes),
                'Metadata' => $this->s3Metadata($write),
            ]),
            new AwsException(
                'conditional delete conflict',
                $client->getCommand('DeleteObject'),
                ['response' => new Response(412)],
            ),
        );
        $objects = new S3AtomicDocumentObjectStore($client, 'private-bucket', 'private-prefix');

        $this->assertSame(0, (new OrphanDocumentReconciler($objects, 86_400))->reconcile($now));
        $this->assertCount(0, $handler);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'schema_version' => 1,
            'document_type' => 'invoice',
            'invoice_kind' => 'invoice',
            'series_uuid' => '018f4ca3-224d-7d8d-9f00-101010101010',
            'document_number' => 'RE-2026-0042',
            'revision_number' => 1,
            'company' => [
                'name' => 'Ledgerline GmbH',
                'street' => 'Example Street 1',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'billing@ledgerline.example',
            ],
            'customer' => [
                'name' => '<script>alert("x")</script>',
                'email' => 'customer@example.com',
                'street' => 'Customer Street 2',
                'postal_code' => '20095',
                'city' => 'Hamburg',
                'internal_note' => 'Internal only',
                'logo_url' => 'https://attacker.example/logo.png',
            ],
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'lines' => [
                [
                    'description' => 'Consulting',
                    'quantity' => '2.5000',
                    'quantity_scaled' => 25_000,
                    'unit' => 'hour',
                    'unit_price_minor' => 10_000,
                    'tax_rate_basis_points' => 1900,
                    'kind' => 'service',
                    'product_id' => null,
                ],
                [
                    'description' => 'Hardware',
                    'quantity' => '1.0000',
                    'quantity_scaled' => 10_000,
                    'unit' => 'piece',
                    'unit_price_minor' => 2_500,
                    'tax_rate_basis_points' => 700,
                    'kind' => 'hardware',
                    'product_id' => 9,
                ],
            ],
            'discount' => [
                'basis_points' => 1000,
                'fixed_minor' => 0,
                'currency' => 'EUR',
            ],
            'totals' => [
                'net_minor' => 24_750,
                'vat_minor' => 4_433,
                'gross_minor' => 29_183,
                'discount_minor' => 2_750,
                'currency' => 'EUR',
                'tax_breakdowns' => [
                    [
                        'tax_rate_basis_points' => 700,
                        'net_minor' => 2_250,
                        'vat_minor' => 158,
                        'gross_minor' => 2_408,
                    ],
                    [
                        'tax_rate_basis_points' => 1900,
                        'net_minor' => 22_500,
                        'vat_minor' => 4_275,
                        'gross_minor' => 26_775,
                    ],
                ],
            ],
        ];
    }

    private function invoiceRepository(): EloquentInvoiceRepository
    {
        $clock = new SystemClock;

        return new EloquentInvoiceRepository(new EloquentIdempotencyStore($clock), $clock);
    }

    private function invoiceDraft(): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData('Consulting', '1.0000', 10_000, 1_900, 'hour', null, 'service')],
            discount: Discount::none('EUR'),
        );
    }

    private function finalizeCommand(
        EloquentInvoiceRepository $repository,
        FlysystemDocumentStorage $storage,
    ): FinalizeInvoice {
        return new FinalizeInvoice(
            $repository,
            new LockedInvoiceNumberAllocator,
            new LegacyStockLedgerAdapter,
            new BladeDocumentRenderer(app('view')),
            $storage,
            new NullLogger,
        );
    }

    /** @return array{User, string, int, string, string} */
    private function publishedInvoice(
        ?User $owner = null,
        string $invoiceUuid = '018f4ca3-224d-7d8d-9f00-919191919191',
        string $number = 'RE-2026-0042',
    ): array {
        $owner ??= User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $bytes = '%PDF-immutable-invoice';
        $ownershipToken = hash('sha256', $invoiceUuid);
        $path = sprintf(
            'finance/revisions/%s/%s.pdf',
            substr($ownershipToken, 0, 2),
            $ownershipToken,
        );
        $now = now();
        $snapshot = $this->snapshot();
        $snapshot['series_uuid'] = $invoiceUuid;
        $snapshot['document_number'] = $number;

        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $invoiceUuid,
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => 24_750,
            'vat_minor' => 4_433,
            'gross_minor' => 29_183,
            'currency' => 'EUR',
            'pdf_path' => $path,
            'pdf_sha256' => hash('sha256', $bytes),
            'published_at' => $now,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        DB::table('finance_invoices')->insert([
            'user_id' => $owner->id,
            'uuid' => $invoiceUuid,
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => 'invoice',
            'number' => $number,
            'year' => 2026,
            'sequence' => $number === 'RE-2026-0042' ? 42 : 43,
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'workflow_status' => 'finalized',
            'finalized_at' => $now,
            'allocated_minor' => 0,
            'open_minor' => 29_183,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Storage::disk('invoice-pdfs')->put($path, $bytes);

        return [$owner, $invoiceUuid, $revisionId, $path, $bytes];
    }

    /** @return array{string, string} */
    private function storageDirectories(): array
    {
        $base = storage_path('framework/testing/invoice-pdf-'.bin2hex(random_bytes(8)));
        $root = $base.'-objects';
        $locks = $base.'-locks';
        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($locks);

        return [$root, $locks];
    }

    private function storageWrite(string $token, string $proof, string $bytes): DocumentStorageWrite
    {
        return new DocumentStorageWrite($token, $proof, hash('sha256', $bytes));
    }

    /** @return array{ledgerline-proof: string, ledgerline-sha256: string, ledgerline-generation: string} */
    private function s3Metadata(DocumentStorageWrite $write): array
    {
        return [
            'ledgerline-proof' => $write->cleanupProof,
            'ledgerline-sha256' => $write->sha256,
            'ledgerline-generation' => $write->generation(),
        ];
    }

    private function objectPath(string $root, string $path): string
    {
        return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function deleteDirectory(string $path): void
    {
        if (str_starts_with($path, storage_path('framework/testing/invoice-pdf-'))) {
            File::deleteDirectory($path);
        }
    }

    private function s3Client(MockHandler $handler): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => $handler,
        ]);
    }

    private function referenceDocumentPath(string $path, string $sha256): void
    {
        $owner = User::factory()->create();
        $now = now();
        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => '018f4ca3-224d-7d8d-9f00-'.bin2hex(random_bytes(6)),
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_document_revisions')->insert([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'status' => 'published',
            'snapshot' => json_encode($this->snapshot(), JSON_THROW_ON_ERROR),
            'net_minor' => 24_750,
            'vat_minor' => 4_433,
            'gross_minor' => 29_183,
            'currency' => 'EUR',
            'pdf_path' => $path,
            'pdf_sha256' => $sha256,
            'published_at' => $now,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
    }
}

final class InvoiceS3CommandLog
{
    /** @var list<CommandInterface> */
    private array $commands = [];

    public function record(CommandInterface $command): void
    {
        $this->commands[] = $command;
    }

    public function get(int $index): CommandInterface
    {
        return $this->commands[$index] ?? throw new \LogicException('S3 command was not recorded.');
    }
}
