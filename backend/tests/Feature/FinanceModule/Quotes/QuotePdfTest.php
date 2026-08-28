<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Modules\Finance\Application\Commands\PublishDocumentRevision;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\BladeDocumentRenderer;
use App\Modules\Finance\Infrastructure\Pdf\FlysystemDocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\LocalAtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Pdf\QuotePdfViewModel;
use App\Modules\Finance\Infrastructure\Pdf\S3AtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_production_storage_allows_only_one_concurrent_writer_for_a_capability(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $barrier = $root.DIRECTORY_SEPARATOR.'start';
        $token = str_repeat('71', 32);
        $script = <<<'PHP'
            require $argv[1].'/vendor/autoload.php';
            $bytes = '%PDF-concurrent';
            $write = new App\Modules\Finance\Application\DTOs\DocumentStorageWrite(
                $argv[4],
                $argv[5],
                hash('sha256', $bytes),
            );
            $storage = new App\Modules\Finance\Infrastructure\Pdf\FlysystemDocumentStorage(
                new App\Modules\Finance\Infrastructure\Pdf\LocalAtomicDocumentObjectStore($argv[2], $argv[3]),
            );
            while (! file_exists($argv[6])) {
                usleep(1_000);
            }
            try {
                $storage->putPdf('series', $bytes, $write);
                echo 'won';
            } catch (LogicException) {
                echo 'lost';
            }
            PHP;
        $first = new Process([
            PHP_BINARY, '-r', $script, base_path(), $root, $locks, $token, str_repeat('72', 32), $barrier,
        ]);
        $second = new Process([
            PHP_BINARY, '-r', $script, base_path(), $root, $locks, $token, str_repeat('73', 32), $barrier,
        ]);

        try {
            $first->start();
            $second->start();
            touch($barrier);
            $first->wait();
            $second->wait();

            $this->assertSame(['lost', 'won'], collect([$first->getOutput(), $second->getOutput()])->sort()->values()->all());
            $this->assertSame(1, count(glob($root.'/finance/revisions/*/*.pdf') ?: []));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_stale_cleanup_preserves_a_new_generation_and_current_cleanup_removes_it(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        $token = str_repeat('74', 32);
        $bytes = '%PDF-generation';
        $old = new DocumentStorageWrite($token, str_repeat('75', 32), hash('sha256', $bytes));
        $current = new DocumentStorageWrite($token, str_repeat('76', 32), hash('sha256', $bytes));

        try {
            $stored = $storage->putPdf('series', $bytes, $old);
            $storage->delete($old);
            $storage->putPdf('series', $bytes, $current);

            $storage->delete($old);
            $this->assertSame($bytes, file_get_contents($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $stored->path)));

            $storage->delete($current);
            $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $stored->path));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_s3_storage_uses_conditional_create_and_generation_bound_conditional_delete(): void
    {
        $commands = new S3CommandLog;
        $bytes = '%PDF-s3';
        $lastModified = new DateTimeImmutable('2026-08-28T10:11:12.123456Z');
        $write = new DocumentStorageWrite(
            str_repeat('77', 32),
            str_repeat('78', 32),
            hash('sha256', $bytes),
        );
        $handler = new MockHandler([
            static function (CommandInterface $command) use ($commands): Result {
                $commands->record($command);

                return new Result(['ETag' => '"etag-created"']);
            },
            new Result([
                'ETag' => '"etag-created"',
                'LastModified' => $lastModified,
                'ContentLength' => strlen($bytes),
                'Metadata' => [
                    'ledgerline-proof' => $write->cleanupProof,
                    'ledgerline-sha256' => $write->sha256,
                    'ledgerline-generation' => $write->generation(),
                ],
            ]),
            static function (CommandInterface $command) use ($commands): Result {
                $commands->record($command);

                return new Result;
            },
        ]);
        $store = new S3AtomicDocumentObjectStore($this->s3Client($handler), 'private-bucket', 'tenant-prefix');
        $path = 'finance/revisions/77/'.str_repeat('77', 32).'.pdf';

        $store->create($path, $bytes, $write);
        $store->deleteIfOwned($path, $write);

        $put = $commands->get(0);
        $delete = $commands->get(1);
        $metadata = $put['Metadata'];
        $this->assertIsArray($metadata);
        $this->assertSame('PutObject', $put->getName());
        $this->assertSame('*', $put['IfNoneMatch']);
        $this->assertSame('tenant-prefix/'.$path, $put['Key']);
        $this->assertSame($write->cleanupProof, $metadata['ledgerline-proof']);
        $this->assertSame('DeleteObject', $delete->getName());
        $this->assertSame('"etag-created"', $delete['IfMatch']);
        $this->assertSame($lastModified, $delete['IfMatchLastModifiedTime']);
        $this->assertSame(strlen($bytes), $delete['IfMatchSize']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_s3_stale_cleanup_does_not_issue_a_delete_for_a_new_generation(): void
    {
        $bytes = '%PDF-s3-generation';
        $stale = new DocumentStorageWrite(
            str_repeat('79', 32),
            str_repeat('7a', 32),
            hash('sha256', $bytes),
        );
        $current = new DocumentStorageWrite(
            $stale->ownershipToken,
            str_repeat('7b', 32),
            $stale->sha256,
        );
        $handler = new MockHandler([new Result([
            'ETag' => '"etag-current"',
            'Metadata' => [
                'ledgerline-proof' => $current->cleanupProof,
                'ledgerline-sha256' => $current->sha256,
                'ledgerline-generation' => $current->generation(),
            ],
        ])]);
        $store = new S3AtomicDocumentObjectStore($this->s3Client($handler), 'private-bucket');

        $store->deleteIfOwned('finance/revisions/79/'.str_repeat('79', 32).'.pdf', $stale);

        $this->assertCount(0, $handler);
    }

    public function test_production_document_ports_are_bound_to_quote_pdf_adapters(): void
    {
        $this->configureLocalDocumentDisk();

        $this->assertInstanceOf(BladeDocumentRenderer::class, app(DocumentRenderer::class));
        $this->assertInstanceOf(FlysystemDocumentStorage::class, app(DocumentStorage::class));
    }

    public function test_production_publication_is_byte_verified_immutable_and_idempotent(): void
    {
        [, $diskName] = $this->configureLocalDocumentDisk();
        [$owner, $revisionId] = $this->draftRevision();
        $this->actingAs($owner);
        $command = app(PublishDocumentRevision::class);

        $published = $command->handle(new DocumentRevisionId($revisionId));
        $retry = $command->handle(new DocumentRevisionId($revisionId));
        $bytes = Storage::disk($diskName)->get($published->path);

        $this->assertEquals($published, $retry);
        $this->assertIsString($bytes);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(hash('sha256', $bytes), $published->sha256);
        $this->assertMatchesRegularExpression(
            '#\Afinance/revisions/[a-f0-9]{2}/[a-f0-9]{64}\.pdf\z#',
            $published->path,
        );
        $pdfs = array_values(array_filter(
            Storage::disk($diskName)->allFiles(),
            static fn (string $path): bool => str_ends_with($path, '.pdf'),
        ));
        $this->assertCount(1, $pdfs);
    }

    public function test_production_publication_compensates_the_owned_object_after_database_failure(): void
    {
        [, $diskName] = $this->configureLocalDocumentDisk();
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

        $this->assertSame([], Storage::disk($diskName)->allFiles());
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
        [$root, $locks] = $this->storageDirectories();
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        $token = str_repeat('ab', 32);
        $path = "finance/revisions/ab/{$token}.pdf";
        $firstWrite = $this->storageWrite($token, str_repeat('ad', 32), '%PDF-first');

        try {
            $stored = $storage->putPdf(
                '018f4ca3-224d-7d8d-9f00-101010101010',
                '%PDF-first',
                $firstWrite,
            );

            $this->assertSame($path, $stored->path);
            $this->assertSame(hash('sha256', '%PDF-first'), $stored->sha256);
            $this->assertSame('%PDF-first', file_get_contents($this->objectPath($root, $path)));

            $otherToken = str_repeat('ac', 32);
            $other = $storage->putPdf(
                '018f4ca3-224d-7d8d-9f00-101010101010',
                '%PDF-first',
                $this->storageWrite($otherToken, str_repeat('ae', 32), '%PDF-first'),
            );
            $this->assertNotSame($stored->path, $other->path);
            $this->assertFileExists($this->objectPath($root, $other->path));

            $storage->putPdf(
                '018f4ca3-224d-7d8d-9f00-101010101010',
                '%PDF-second',
                $this->storageWrite($token, str_repeat('af', 32), '%PDF-second'),
            );
            $this->fail('A colliding capability unexpectedly overwrote its object.');
        } catch (LogicException $exception) {
            $this->assertSame('The document capability is already in use.', $exception->getMessage());
            $this->assertSame('%PDF-first', file_get_contents($this->objectPath($root, $path)));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_storage_cleanup_derives_only_the_supplied_capability_path(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        $ownedToken = str_repeat('cd', 32);
        $otherToken = str_repeat('ef', 32);
        $ownedWrite = $this->storageWrite($ownedToken, str_repeat('ce', 32), '%PDF-owned');
        $otherWrite = $this->storageWrite($otherToken, str_repeat('f0', 32), '%PDF-other');

        try {
            $ownedPath = $storage->putPdf('series', '%PDF-owned', $ownedWrite)->path;
            $otherPath = $storage->putPdf('series', '%PDF-other', $otherWrite)->path;
            $storage->delete($ownedWrite);

            $this->assertFileDoesNotExist($this->objectPath($root, $ownedPath));
            $this->assertFileExists($this->objectPath($root, $otherPath));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_storage_failure_is_reported_and_the_same_capability_can_be_retried_after_cleanup(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));
        $token = str_repeat('12', 32);
        $original = $this->storageWrite($token, str_repeat('13', 32), '%PDF-original');
        $retry = $this->storageWrite($token, str_repeat('14', 32), '%PDF-retry');

        try {
            $storage->putPdf('series', '%PDF-original', $original);

            try {
                $storage->putPdf('series', '%PDF-retry', $retry);
                $this->fail('A colliding storage write unexpectedly succeeded.');
            } catch (LogicException $exception) {
                $this->assertSame('The document capability is already in use.', $exception->getMessage());
            }

            $storage->delete($original);
            $stored = $storage->putPdf('series', '%PDF-retry', $retry);
            $this->assertSame("finance/revisions/12/{$token}.pdf", $stored->path);
            $this->assertSame(hash('sha256', '%PDF-retry'), $stored->sha256);
            $this->assertSame('%PDF-retry', file_get_contents($this->objectPath($root, $stored->path)));
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_storage_reports_a_failed_private_write(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $blockedRoot = $root.DIRECTORY_SEPARATOR.'not-a-directory';
        file_put_contents($blockedRoot, 'blocked');
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($blockedRoot, $locks));

        try {
            $storage->putPdf(
                'series',
                '%PDF-failed',
                $this->storageWrite(str_repeat('34', 32), str_repeat('35', 32), '%PDF-failed'),
            );
            $this->fail('Writing below a regular file unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The document storage directory could not be created.', $exception->getMessage());
        } finally {
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
        }
    }

    public function test_storage_rejects_non_canonical_or_traversal_capabilities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DocumentStorageWrite('../'.str_repeat('a', 64), str_repeat('36', 32), str_repeat('a', 64));
    }

    public function test_storage_rejects_non_pdf_bytes_before_writing(): void
    {
        [$root, $locks] = $this->storageDirectories();
        $storage = new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore($root, $locks));

        $this->expectException(InvalidArgumentException::class);
        try {
            $storage->putPdf(
                'series',
                '<html>not a PDF</html>',
                $this->storageWrite(
                    str_repeat('56', 32),
                    str_repeat('57', 32),
                    '<html>not a PDF</html>',
                ),
            );
        } finally {
            $this->assertSame([], glob($root.'/finance/revisions/*/*.pdf') ?: []);
            $this->deleteDirectory($root);
            $this->deleteDirectory($locks);
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

    /** @return array{string, string} */
    private function storageDirectories(): array
    {
        $base = storage_path('framework/testing/quote-pdf-'.bin2hex(random_bytes(8)));
        $root = $base.'-objects';
        $locks = $base.'-locks';
        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($locks);

        return [$root, $locks];
    }

    private function deleteDirectory(string $path): void
    {
        if (str_starts_with($path, storage_path('framework/testing/quote-pdf-'))) {
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

    /** @return array{string, string} */
    private function configureLocalDocumentDisk(): array
    {
        $diskName = 'quote-pdf-atomic';
        $root = storage_path('framework/testing/'.$diskName.'-'.bin2hex(random_bytes(8)));
        $lockRoot = storage_path('framework/finance-document-locks/'.hash('sha256', $root));
        File::ensureDirectoryExists($root);
        config()->set('files.disk', $diskName);
        config()->set('filesystems.disks.'.$diskName, [
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);
        Storage::forgetDisk($diskName);
        $this->beforeApplicationDestroyed(function () use ($root, $lockRoot, $diskName): void {
            Storage::forgetDisk($diskName);
            $this->deleteDirectory($root);
            if (str_starts_with($lockRoot, storage_path('framework/finance-document-locks/'))) {
                File::deleteDirectory($lockRoot);
            }
        });

        return [$root, $diskName];
    }

    private function storageWrite(string $token, string $proof, string $bytes): DocumentStorageWrite
    {
        return new DocumentStorageWrite($token, $proof, hash('sha256', $bytes));
    }

    private function objectPath(string $root, string $path): string
    {
        return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}

final class S3CommandLog
{
    /** @var list<CommandInterface> */
    private array $commands = [];

    public function record(CommandInterface $command): void
    {
        $this->commands[] = $command;
    }

    public function get(int $index): CommandInterface
    {
        return $this->commands[$index] ?? throw new LogicException('S3 command was not recorded.');
    }
}
