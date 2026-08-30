<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\Commands\Quotes\PublishQuote;
use App\Modules\Finance\Application\Commands\Quotes\SendQuote;
use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteMailer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteToInvoicePort;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class QuoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_routes_are_additive_uuid_scoped_and_protected_by_the_finance_stack(): void
    {
        $routes = [
            'api.finance-v2.quotes.index' => ['GET', 'api/v1/finance-v2/quotes'],
            'api.finance-v2.quotes.preview' => ['POST', 'api/v1/finance-v2/quotes/preview'],
            'api.finance-v2.quotes.store' => ['POST', 'api/v1/finance-v2/quotes'],
            'api.finance-v2.quotes.show' => ['GET', 'api/v1/finance-v2/quotes/{quote}'],
            'api.finance-v2.quotes.revisions.index' => ['GET', 'api/v1/finance-v2/quotes/{quote}/revisions'],
            'api.finance-v2.quotes.draft.update' => ['PUT', 'api/v1/finance-v2/quotes/{quote}/draft'],
            'api.finance-v2.quotes.draft.discard' => ['DELETE', 'api/v1/finance-v2/quotes/{quote}/draft'],
            'api.finance-v2.quotes.versions.store' => ['POST', 'api/v1/finance-v2/quotes/{quote}/versions'],
            'api.finance-v2.quotes.publish' => ['POST', 'api/v1/finance-v2/quotes/{quote}/publish'],
            'api.finance-v2.quotes.send' => ['POST', 'api/v1/finance-v2/quotes/{quote}/send'],
            'api.finance-v2.quotes.accept' => ['POST', 'api/v1/finance-v2/quotes/{quote}/accept'],
            'api.finance-v2.quotes.decline' => ['POST', 'api/v1/finance-v2/quotes/{quote}/decline'],
            'api.finance-v2.quotes.duplicate' => ['POST', 'api/v1/finance-v2/quotes/{quote}/duplicate'],
            'api.finance-v2.quotes.convert.invoice' => ['POST', 'api/v1/finance-v2/quotes/{quote}/conversions/invoice'],
        ];

        foreach ($routes as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('abilities:device', $middleware);
            $this->assertContains('module:finance', $middleware);
            $this->assertContains('throttle:120,1', $middleware);
        }

        $this->getJson('/api/v1/finance-v2/quotes')->assertUnauthorized();

        $disabled = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $disabledToken = $disabled->createToken('device', ['device'])->plainTextToken;
        $this->withToken($disabledToken)->getJson('/api/v1/finance-v2/quotes')->assertForbidden();
    }

    public function test_create_requires_exact_decimal_strings_and_an_idempotency_header(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $payload = $this->draftPayload();

        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $payload['lines'][0]['quantity'] = 2.5;
        $this->withToken($token)->withHeader('Idempotency-Key', 'create-http-1')
            ->postJson('/api/v1/finance-v2/quotes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity']);

        $payload = $this->draftPayload();
        $response = $this->withToken($token)->withHeader('Idempotency-Key', 'create-http-1')
            ->postJson('/api/v1/finance-v2/quotes', $payload)
            ->assertCreated()
            ->assertHeader('ETag', '"0"')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('effective_status', 'draft')
            ->assertJsonPath('version', 0)
            ->assertJsonPath('totals.net_minor', '22500')
            ->assertJsonPath('totals.vat_minor', '4275')
            ->assertJsonPath('totals.gross_minor', '26775')
            ->assertJsonPath('totals.currency', 'EUR')
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('pdf_path');

        $this->assertIsString($response->json('id'));
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());

        $different = $this->draftPayload('Different request');
        $this->withToken($token)->withHeader('Idempotency-Key', 'create-http-1')
            ->postJson('/api/v1/finance-v2/quotes', $different)
            ->assertConflict()
            ->assertJsonPath('error', 'idempotency_key_reused');
    }

    public function test_control_totals_require_canonical_bounded_integer_strings_without_precision_loss(): void
    {
        [, $token] = $this->ownerAndToken();

        foreach ([22500, '+22500', '022500', '-0', ((string) PHP_INT_MAX).'0', ((string) PHP_INT_MIN).'0'] as $invalid) {
            $payload = $this->draftPayload();
            $payload['control_net_minor'] = $invalid;
            $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['control_net_minor']);
        }

        $payload = $this->draftPayload();
        $payload['control_net_minor'] = '22500';
        $payload['control_vat_minor'] = '4275';
        $payload['control_gross_minor'] = '26775';
        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $payload)
            ->assertOk()
            ->assertJsonPath('gross_minor', '26775');

        unset($payload['control_vat_minor'], $payload['control_gross_minor']);
        foreach (['9007199254740993', (string) PHP_INT_MAX, (string) PHP_INT_MIN] as $boundedButOutsideMoneyDomain) {
            $payload['control_net_minor'] = $boundedButOutsideMoneyDomain;
            $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $payload)
                ->assertUnprocessable()
                ->assertExactJson(['error' => 'invalid_money']);
        }

        $negative = $this->draftPayload();
        $negative['lines'][0]['quantity'] = '-1.0000';
        $negative['discount_type'] = 'none';
        $negative['discount_value'] = null;
        $negative['control_net_minor'] = '-10000';
        $negative['control_vat_minor'] = '-1900';
        $negative['control_gross_minor'] = '-11900';
        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $negative)
            ->assertOk()
            ->assertJsonPath('net_minor', '-10000')
            ->assertJsonPath('vat_minor', '-1900')
            ->assertJsonPath('gross_minor', '-11900');
    }

    public function test_preview_is_read_only_and_returns_server_authoritative_minor_units(): void
    {
        [$owner, $token] = $this->ownerAndToken();

        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $this->draftPayload())
            ->assertOk()
            ->assertExactJson([
                'net_minor' => '22500',
                'vat_minor' => '4275',
                'gross_minor' => '26775',
                'discount_minor' => '2500',
                'currency' => 'EUR',
                'tax_breakdowns' => [[
                    'tax_rate_basis_points' => '1900',
                    'net_minor' => '22500',
                    'vat_minor' => '4275',
                    'gross_minor' => '26775',
                ]],
                'issue_date' => '2026-08-28',
                'valid_until' => '2026-09-27',
            ]);

        $this->assertSame(0, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
    }

    public function test_quote_resources_preserve_exact_nested_integer_values_above_javascript_safe_range(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Exact integers');
        $seriesId = (int) DB::table('finance_document_series')->where('uuid', $quote->id->uuid)->value('id');
        $large = 9_007_199_254_740_993;
        $larger = 9_007_199_254_740_994;
        $payload = json_decode((string) DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $payload['lines'][0]['quantity_scaled'] = $large;
        $payload['lines'][0]['unit_price_minor'] = $larger;
        $payload['lines'][0]['tax_rate_basis_points'] = 1900;
        $payload['discount'] = ['type' => 'fixed', 'value' => '1.00', 'minor' => $large, 'currency' => 'EUR'];
        $payload['totals'] = [
            'net_minor' => $large,
            'vat_minor' => 1,
            'gross_minor' => $larger,
            'discount_minor' => $large,
            'currency' => 'EUR',
            'tax_breakdowns' => [[
                'tax_rate_basis_points' => 1900,
                'net_minor' => $large,
                'vat_minor' => 1,
                'gross_minor' => $larger,
            ]],
        ];
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->update([
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'net_minor' => $large,
            'vat_minor' => 1,
            'gross_minor' => $larger,
        ]);

        $detail = $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid)->assertOk();
        $this->assertSame((string) $large, $detail->json('totals.net_minor'), json_encode($detail->json(), JSON_THROW_ON_ERROR));
        $detail->assertJsonPath('draft.lines.0.quantity_scaled', (string) $large)
            ->assertJsonPath('draft.lines.0.unit_price_minor', (string) $larger)
            ->assertJsonPath('draft.lines.0.tax_rate_basis_points', '1900')
            ->assertJsonPath('draft.discount.minor', (string) $large)
            ->assertJsonPath('draft.totals.tax_breakdowns.0.gross_minor', (string) $larger);

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes')->assertOk()
            ->assertJsonPath('data.0.totals.net_minor', (string) $large)
            ->assertJsonPath('data.0.draft.lines.0.quantity_scaled', (string) $large);

        $revisionId = $this->publishFixture($owner, $seriesId);
        $revisionSnapshot = $payload;
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'snapshot' => json_encode($revisionSnapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $large,
            'vat_minor' => 1,
            'gross_minor' => $larger,
        ]);

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid.'/revisions')->assertOk()
            ->assertJsonPath('0.id', $revisionId)
            ->assertJsonPath('0.totals.net_minor', (string) $large)
            ->assertJsonPath('0.snapshot.lines.0.quantity_scaled', (string) $large)
            ->assertJsonPath('0.snapshot.discount.minor', (string) $large)
            ->assertJsonPath('0.snapshot.totals.tax_breakdowns.0.gross_minor', (string) $larger);
    }

    public function test_unsupported_expense_lines_are_rejected_at_the_http_boundary(): void
    {
        [, $token] = $this->ownerAndToken();
        $payload = $this->draftPayload();
        $payload['lines'][0]['kind'] = 'expense';

        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.kind']);
    }

    public function test_domain_input_failures_use_stable_machine_codes_instead_of_exception_prose(): void
    {
        [, $token] = $this->ownerAndToken();
        $payload = $this->draftPayload();
        $payload['lines'][0]['tax_rate'] = '100.01';

        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/preview', $payload)
            ->assertUnprocessable()
            ->assertExactJson(['error' => 'invalid_tax_rate']);
    }

    public function test_list_validates_filters_and_returns_stable_pagination_without_other_owner_data(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        [$other] = $this->ownerAndToken();
        $this->createQuote($owner, 'Alpha');
        $this->createQuote($owner, 'Beta');
        $this->createQuote($other, 'Foreign needle');

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes?per_page=101&status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status']);

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes?q=needle')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $page = $this->withToken($token)->getJson('/api/v1/finance-v2/quotes?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
        $this->assertStringContainsString('page=1', (string) $page->json('links.prev'));
    }

    public function test_detail_is_owner_scoped(): void
    {
        [$owner] = $this->ownerAndToken();
        [$other, $otherToken] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Owner only');

        $this->assertNotSame($owner->id, $other->id);
        $this->withToken($otherToken)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid)
            ->assertNotFound();
    }

    public function test_detail_and_revision_resources_do_not_leak_storage_or_operation_fields(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Owner resource');

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid)
            ->assertOk()
            ->assertJsonPath('id', $quote->id->uuid)
            ->assertJsonPath('has_pending_draft', true)
            ->assertJsonPath('delivery', null)
            ->assertJsonPath('conversions', []);

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid.'/revisions')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_revision_history_endpoint_returns_the_owner_scoped_immutable_projection(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Revision history');
        $seriesId = (int) DB::table('finance_document_series')->where('uuid', $quote->id->uuid)->value('id');
        $revisionId = $this->publishFixture($owner, $seriesId);

        $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid.'/revisions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $revisionId)
            ->assertJsonPath('0.pdf_sha256', str_repeat('a', 64))
            ->assertJsonMissingPath('0.pdf_path')
            ->assertJsonMissingPath('0.user_id');
    }

    public function test_delivery_and_conversion_summaries_are_owner_safe_and_exposed_without_sensitive_fields(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Projected');
        $seriesId = (int) DB::table('finance_document_series')->where('uuid', $quote->id->uuid)->value('id');
        $revisionId = $this->publishFixture($owner, $seriesId);
        DB::table('finance_quote_deliveries')->insert([
            'uuid' => '018f4ca3-224d-7d8d-9f00-848484848484',
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'recipient' => 'billing@example.com',
            'recipient_domain' => 'example.com',
            'message_id' => '<018f4ca3-224d-7d8d-9f00-848484848484@quotes.ledgerline>',
            'state' => 'sent',
            'attempts' => 1,
            'last_error_code' => null,
            'queued_at' => now(),
            'sent_at' => now(),
            'failed_at' => null,
        ]);
        DB::table('finance_quote_conversions')->insert([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'source_revision_id' => $revisionId,
            'target_type' => 'invoice',
            'target_reference' => 'invoice:77',
            'target_id' => null,
            'created_at' => now(),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid)
            ->assertOk()
            ->assertJsonPath('delivery.uuid', '018f4ca3-224d-7d8d-9f00-848484848484')
            ->assertJsonPath('delivery.state', 'sent')
            ->assertJsonPath('conversions.0.target_reference', 'invoice:77')
            ->assertJsonMissingPath('delivery.message_id')
            ->assertJsonMissingPath('delivery.recipient');
        $this->assertArrayNotHasKey('user_id', $response->json());
    }

    public function test_draft_version_conflict_returns_409_with_machine_code_and_current_resource(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Conflict');
        $payload = $this->draftPayload('Changed');
        $payload['version'] = 99;

        $this->withToken($token)->putJson('/api/v1/finance-v2/quotes/'.$quote->id->uuid.'/draft', $payload)
            ->assertConflict()
            ->assertHeader('ETag', '"0"')
            ->assertJsonPath('error', 'version_conflict')
            ->assertJsonPath('current.id', $quote->id->uuid)
            ->assertJsonPath('current.version', 0)
            ->assertJsonPath('current.totals.gross_minor', '26775');
    }

    public function test_named_actions_validate_idempotency_revision_and_version_at_the_http_boundary(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Actions');
        $base = '/api/v1/finance-v2/quotes/'.$quote->id->uuid;

        foreach (['publish', 'send', 'accept', 'decline', 'duplicate', 'conversions/invoice'] as $action) {
            $this->withToken($token)->postJson($base.'/'.$action, [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['idempotency_key', 'version']);
        }

        $this->withToken($token)->postJson($base.'/accept', ['version' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key', 'expected_revision_id']);

        $this->withToken($token)->withHeader('Idempotency-Key', 'accept-unpublished')
            ->postJson($base.'/accept', ['version' => 0, 'expected_revision_id' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'quote_not_published');
    }

    public function test_send_returns_202_for_the_first_dispatch_and_200_for_an_exact_idempotent_replay(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Send statuses');
        $seriesId = (int) DB::table('finance_document_series')->where('uuid', $quote->id->uuid)->value('id');
        $this->publishFixture($owner, $seriesId);
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->delete();
        config()->set('files.disk', 'local');
        $mailer = new ApiQuoteMailer;
        app()->instance(QuoteMailer::class, $mailer);
        app()->instance(SendQuote::class, new SendQuote(
            app(QuoteRepository::class),
            app(QuoteOperationRepository::class),
            $mailer,
            app(PublishQuote::class),
        ));
        $url = '/api/v1/finance-v2/quotes/'.$quote->id->uuid.'/send';
        $payload = ['version' => 0, 'recipient' => 'billing@example.com', 'change_reason' => null];

        $this->withToken($token)->withHeader('Idempotency-Key', 'api-send-replay')->postJson($url, $payload)
            ->assertAccepted();

        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'accepted']);
        $mailer->configured = false;
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-send-replay')->postJson($url, $payload)
            ->assertOk();

        $changed = $payload;
        $changed['change_reason'] = 'Changed after the successful request';
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-send-replay')->postJson($url, $changed)
            ->assertJsonPath('error', 'idempotency_key_reused')
            ->assertConflict();

        $this->assertSame(1, $mailer->dispatches);
        $this->assertSame(1, $mailer->configuredChecks);
        $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
    }

    public function test_every_quote_mutation_has_a_successful_http_path(): void
    {
        [$owner, $token] = $this->ownerAndToken();

        $updated = $this->createQuote($owner, 'Update');
        $updatePayload = $this->draftPayload('Updated through HTTP');
        $updatePayload['version'] = 0;
        $this->withToken($token)->putJson('/api/v1/finance-v2/quotes/'.$updated->id->uuid.'/draft', $updatePayload)
            ->assertOk()->assertJsonPath('version', 1)->assertJsonPath('draft.title', 'Updated through HTTP')
            ->assertJsonPath('totals.gross_minor', '26775');

        $discarded = $this->createQuote($owner, 'Discard');
        $discardSeries = (int) DB::table('finance_document_series')->where('uuid', $discarded->id->uuid)->value('id');
        $discardRevision = $this->publishFixture($owner, $discardSeries);
        DB::table('finance_quote_drafts')->where('document_series_id', $discardSeries)->update(['based_on_revision_id' => $discardRevision]);
        $this->withToken($token)->deleteJson('/api/v1/finance-v2/quotes/'.$discarded->id->uuid.'/draft', ['version' => 0])
            ->assertOk()->assertJsonPath('version', 1)->assertJsonPath('has_pending_draft', false);

        $versioned = $this->createQuote($owner, 'Version');
        $versionSeries = (int) DB::table('finance_document_series')->where('uuid', $versioned->id->uuid)->value('id');
        $this->publishFixture($owner, $versionSeries);
        DB::table('finance_quote_drafts')->where('document_series_id', $versionSeries)->delete();
        $this->withToken($token)->postJson('/api/v1/finance-v2/quotes/'.$versioned->id->uuid.'/versions', ['version' => 0])
            ->assertCreated()->assertJsonPath('version', 1)->assertJsonPath('has_pending_draft', true);

        config()->set('files.disk', 'local');
        app()->instance(DocumentRenderer::class, new ApiDocumentRenderer);
        $published = $this->createQuote($owner, 'Publish');
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-publish-success')
            ->postJson('/api/v1/finance-v2/quotes/'.$published->id->uuid.'/publish', ['version' => 0, 'change_reason' => null])
            ->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('version', 1);

        $accepted = $this->publishedQuote($owner, 'Accept');
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-accept-success')
            ->postJson('/api/v1/finance-v2/quotes/'.$accepted['quote']->id->uuid.'/accept', [
                'version' => 0,
                'expected_revision_id' => $accepted['revision'],
            ])->assertOk()->assertJsonPath('status', 'accepted')->assertJsonPath('version', 1);

        $declined = $this->publishedQuote($owner, 'Decline');
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-decline-success')
            ->postJson('/api/v1/finance-v2/quotes/'.$declined['quote']->id->uuid.'/decline', [
                'version' => 0,
                'expected_revision_id' => $declined['revision'],
            ])->assertOk()->assertJsonPath('status', 'declined')->assertJsonPath('version', 1);

        $source = $this->createQuote($owner, 'Duplicate');
        $duplicate = $this->withToken($token)->withHeader('Idempotency-Key', 'api-duplicate-success')
            ->postJson('/api/v1/finance-v2/quotes/'.$source->id->uuid.'/duplicate', ['version' => 0])
            ->assertCreated()->assertJsonPath('status', 'draft')->assertJsonPath('version', 0);
        $this->assertNotSame($source->id->uuid, $duplicate->json('id'));

        app()->instance(QuoteToInvoicePort::class, new ApiQuoteToInvoicePort);
        $converted = $this->publishedQuote($owner, 'Convert');
        DB::table('finance_document_series')->where('uuid', $converted['quote']->id->uuid)->update(['status' => 'accepted']);
        $this->withToken($token)->withHeader('Idempotency-Key', 'api-convert-success')
            ->postJson('/api/v1/finance-v2/quotes/'.$converted['quote']->id->uuid.'/conversions/invoice', [
                'version' => 0,
                'expected_revision_id' => $converted['revision'],
            ])->assertCreated()->assertExactJson([
                'target_reference' => 'invoice-draft:4242',
                'target_id' => null,
            ]);
    }

    public function test_every_quote_mutation_hides_a_foreign_owner_aggregate_with_404(): void
    {
        [$owner] = $this->ownerAndToken();
        [, $foreignToken] = $this->ownerAndToken();
        $quote = $this->createQuote($owner, 'Foreign mutations');
        $base = '/api/v1/finance-v2/quotes/'.$quote->id->uuid;
        $draft = $this->draftPayload('Foreign update');
        $draft['version'] = 0;

        app()->instance(QuoteMailer::class, new ApiQuoteMailer);
        app()->instance(QuoteToInvoicePort::class, new ApiQuoteToInvoicePort);

        $this->withToken($foreignToken)->putJson($base.'/draft', $draft)->assertNotFound();
        $this->withToken($foreignToken)->deleteJson($base.'/draft', ['version' => 0])->assertNotFound();
        $this->withToken($foreignToken)->postJson($base.'/versions', ['version' => 0])->assertNotFound();

        foreach ([
            ['publish', ['version' => 0]],
            ['send', ['version' => 0, 'recipient' => 'billing@example.com']],
            ['accept', ['version' => 0, 'expected_revision_id' => 1]],
            ['decline', ['version' => 0, 'expected_revision_id' => 1]],
            ['duplicate', ['version' => 0]],
            ['conversions/invoice', ['version' => 0, 'expected_revision_id' => 1]],
        ] as $index => [$path, $payload]) {
            $this->withToken($foreignToken)->withHeader('Idempotency-Key', 'foreign-action-'.$index)
                ->postJson($base.'/'.$path, $payload)
                ->assertNotFound();
        }
    }

    public function test_container_bound_legacy_adapter_converts_an_accepted_quote_through_the_api(): void
    {
        $this->assertInstanceOf(LegacyInvoiceDraftAdapter::class, app(QuoteToInvoicePort::class));

        [$owner, $token] = $this->ownerAndToken();
        config()->set('files.disk', 'local');
        app()->instance(DocumentRenderer::class, new ApiDocumentRenderer);
        $quote = $this->createQuote($owner, 'Container conversion');
        $base = '/api/v1/finance-v2/quotes/'.$quote->id->uuid;
        $published = $this->withToken($token)->withHeader('Idempotency-Key', 'api-convert-publish')
            ->postJson($base.'/publish', ['version' => 0])
            ->assertOk();
        $revisionId = $published->json('current_revision.id');
        $this->assertIsInt($revisionId);

        $this->withToken($token)->withHeader('Idempotency-Key', 'api-convert-accept')
            ->postJson($base.'/accept', ['version' => 1, 'expected_revision_id' => $revisionId])
            ->assertOk()->assertJsonPath('status', 'accepted')->assertJsonPath('version', 2);

        $response = $this->withToken($token)->withHeader('Idempotency-Key', 'api-convert-real')
            ->postJson($base.'/conversions/invoice', ['version' => 2, 'expected_revision_id' => $revisionId])
            ->assertCreated();
        $invoiceId = $response->json('target_id');
        $this->assertIsInt($invoiceId);
        $response->assertJsonPath('target_reference', 'legacy-invoice:'.$invoiceId);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);
    }

    public function test_openapi_is_strict_yaml_and_quote_schemas_require_every_emitted_nullable_key(): void
    {
        $document = Yaml::parseFile(base_path('../openapi.yaml'), Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        $this->assertIsArray($document);
        $schemas = $document['components']['schemas'];

        $expectedRequired = [
            'FinanceV2QuotePreview' => ['net_minor', 'vat_minor', 'gross_minor', 'discount_minor', 'currency', 'tax_breakdowns', 'issue_date', 'valid_until'],
            'FinanceV2Quote' => ['id', 'status', 'effective_status', 'partner_id', 'number', 'version', 'has_pending_draft', 'current_revision', 'draft', 'totals', 'conversions', 'delivery', 'published_at', 'accepted_at', 'declined_at', 'converted_at', 'created_at', 'updated_at'],
            'FinanceV2QuoteRevision' => ['id', 'revision_number', 'previous_revision_id', 'status', 'snapshot', 'totals', 'pdf_sha256', 'pdf_url', 'pdf_download_url', 'published_at', 'created_at'],
            'FinanceV2QuoteDelivery' => ['uuid', 'revision_id', 'state', 'attempts', 'last_error_code', 'queued_at', 'sent_at', 'failed_at'],
            'FinanceV2QuotePage' => ['data', 'links', 'meta'],
        ];
        foreach ($expectedRequired as $schema => $required) {
            $this->assertEqualsCanonicalizing($required, $schemas[$schema]['required'] ?? [], $schema);
        }
        $this->assertEqualsCanonicalizing(['first', 'last', 'prev', 'next'], $schemas['FinanceV2QuotePage']['properties']['links']['required'] ?? []);
        $this->assertEqualsCanonicalizing(['current_page', 'per_page', 'total', 'last_page'], $schemas['FinanceV2QuotePage']['properties']['meta']['required'] ?? []);
        $this->assertSame(['service', 'hardware'], $schemas['FinanceV2QuoteLineInput']['properties']['kind']['enum']);
        $this->assertSame('#/components/schemas/FinanceV2QuoteSnapshot', $schemas['FinanceV2QuoteRevision']['properties']['snapshot']['$ref']);
        $this->assertSame('#/components/schemas/FinanceV2QuoteDraft', $schemas['FinanceV2Quote']['properties']['draft']['anyOf'][0]['$ref']);
        $this->assertFalse($schemas['FinanceV2QuoteSnapshot']['additionalProperties']);
        $this->assertFalse($schemas['FinanceV2QuoteDraft']['additionalProperties']);
        $this->assertContains('target_id', $schemas['FinanceV2Quote']['properties']['conversions']['items']['required']);
        $this->assertArrayHasKey('oneOf', $schemas['FinanceV2QuoteUnprocessable']);

        $signedPattern = '^(?:0|-?[1-9][0-9]*)$';
        $unsignedPattern = '^(?:0|[1-9][0-9]*)$';
        foreach ([
            ['FinanceV2Money', 'net_minor'],
            ['FinanceV2Money', 'vat_minor'],
            ['FinanceV2Money', 'gross_minor'],
            ['FinanceV2QuotePreview', 'net_minor'],
            ['FinanceV2QuotePreview', 'vat_minor'],
            ['FinanceV2QuotePreview', 'gross_minor'],
            ['FinanceV2QuotePreview', 'discount_minor'],
            ['FinanceV2QuoteLine', 'quantity_scaled'],
            ['FinanceV2QuoteLine', 'unit_price_minor'],
            ['FinanceV2QuoteCalculatedTotals', 'net_minor'],
            ['FinanceV2QuoteCalculatedTotals', 'vat_minor'],
            ['FinanceV2QuoteCalculatedTotals', 'gross_minor'],
            ['FinanceV2QuoteCalculatedTotals', 'discount_minor'],
        ] as [$schema, $property]) {
            $definition = $schemas[$schema]['properties'][$property];
            $this->assertSame('string', $definition['type'], "{$schema}.{$property}");
            $this->assertSame($signedPattern, $definition['pattern'], "{$schema}.{$property}");
            $this->assertIsString($definition['example'], "{$schema}.{$property}");
        }
        foreach ([
            ['FinanceV2QuoteLine', 'tax_rate_basis_points'],
            ['FinanceV2QuoteDiscount', 'basis_points'],
            ['FinanceV2QuoteDiscount', 'minor'],
        ] as [$schema, $property]) {
            $definition = $schemas[$schema]['properties'][$property];
            $this->assertSame('string', $definition['type'], "{$schema}.{$property}");
            $this->assertSame($unsignedPattern, $definition['pattern'], "{$schema}.{$property}");
            $this->assertIsString($definition['example'], "{$schema}.{$property}");
        }
        foreach (['FinanceV2QuotePreview', 'FinanceV2QuoteCalculatedTotals'] as $schema) {
            $properties = $schemas[$schema]['properties']['tax_breakdowns']['items']['properties'];
            foreach (['net_minor', 'vat_minor', 'gross_minor'] as $property) {
                $this->assertSame('string', $properties[$property]['type'], "{$schema}.tax_breakdowns.{$property}");
                $this->assertSame($signedPattern, $properties[$property]['pattern'], "{$schema}.tax_breakdowns.{$property}");
            }
            $this->assertSame('string', $properties['tax_rate_basis_points']['type']);
            $this->assertSame($unsignedPattern, $properties['tax_rate_basis_points']['pattern']);
        }
        foreach (['control_net_minor', 'control_vat_minor', 'control_gross_minor'] as $property) {
            $definition = $schemas['FinanceV2QuoteDraftInput']['properties'][$property];
            $this->assertSame(['string', 'null'], $definition['type'], $property);
            $this->assertSame($signedPattern, $definition['pattern'], $property);
            $this->assertIsString($definition['example'], $property);
        }
        foreach ([
            '/finance-v2/quotes',
            '/finance-v2/quotes/preview',
            '/finance-v2/quotes/{quote}/publish',
            '/finance-v2/quotes/{quote}/send',
            '/finance-v2/quotes/{quote}/accept',
            '/finance-v2/quotes/{quote}/decline',
            '/finance-v2/quotes/{quote}/duplicate',
            '/finance-v2/quotes/{quote}/conversions/invoice',
        ] as $path) {
            $this->assertSame(
                '#/components/responses/FinanceV2QuoteUnprocessable',
                $document['paths'][$path]['post']['responses']['422']['$ref'],
                $path,
            );
        }
    }

    public function test_openapi_documents_every_quote_v2_route_and_never_documents_client_pdf_upload(): void
    {
        $openapi = file_get_contents(base_path('../openapi.yaml'));
        $this->assertIsString($openapi);

        foreach ([
            '/finance-v2/quotes:',
            '/finance-v2/quotes/preview:',
            '/finance-v2/quotes/{quote}:',
            '/finance-v2/quotes/{quote}/draft:',
            '/finance-v2/quotes/{quote}/versions:',
            '/finance-v2/quotes/{quote}/publish:',
            '/finance-v2/quotes/{quote}/send:',
            '/finance-v2/quotes/{quote}/accept:',
            '/finance-v2/quotes/{quote}/decline:',
            '/finance-v2/quotes/{quote}/duplicate:',
            '/finance-v2/quotes/{quote}/conversions/invoice:',
        ] as $path) {
            $this->assertStringContainsString($path, $openapi);
        }

        $this->assertStringContainsString('FinanceV2QuoteDraftInput:', $openapi);
        $this->assertStringContainsString('FinanceV2QuotePage:', $openapi);
        $this->assertStringNotContainsString('FinanceV2QuotePdfUpload', $openapi);
    }

    /** @return array{User, string} */
    private function ownerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    private function createQuote(User $owner, string $title): mixed
    {
        return app(CreateQuote::class)->handle($owner->id, 'test-'.hash('sha256', $title), new QuoteDraftData(
            $title,
            null,
            ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            '2026-08-28',
            '2026-09-27',
            'EUR',
            [new QuoteLineData('Consulting', '2.5000', 'hour', '100.00', '19.00', 'service', null)],
            'percent',
            '10.00',
            null,
            null,
            null,
        ));
    }

    /** @return array<string, mixed> */
    private function draftPayload(string $title = 'Network refresh'): array
    {
        return [
            'title' => $title,
            'partner_id' => null,
            'customer' => ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            'issue_date' => '2026-08-28',
            'valid_until' => '2026-09-27',
            'currency' => 'EUR',
            'lines' => [[
                'description' => 'Consulting',
                'quantity' => '2.5000',
                'unit' => 'hour',
                'unit_price' => '100.00',
                'tax_rate' => '19.00',
                'kind' => 'service',
                'product_id' => null,
            ]],
            'discount_type' => 'percent',
            'discount_value' => '10.00',
            'intro_text' => null,
            'outro_text' => null,
            'internal_note' => null,
        ];
    }

    private function publishFixture(User $owner, int $seriesId): int
    {
        $number = 'AN-2026-'.str_pad((string) $seriesId, 4, '0', STR_PAD_LEFT);
        $snapshot = $this->draftPayload('Published');
        $snapshot['document_number'] = $number;
        $snapshot['revision_label'] = $number;
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => 22500,
            'vat_minor' => 4275,
            'gross_minor' => 26775,
            'currency' => 'EUR',
            'change_reason' => null,
            'pdf_path' => 'finance/revisions/aa/'.str_repeat('a', 64).'.pdf',
            'pdf_sha256' => str_repeat('a', 64),
            'published_at' => now(),
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'number' => $number,
            'sequence_year' => 2026,
            'sequence_number' => $seriesId,
            'published_at' => now(),
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'sent']);

        return $revisionId;
    }

    /** @return array{quote: mixed, revision: int} */
    private function publishedQuote(User $owner, string $title): array
    {
        $quote = $this->createQuote($owner, $title);
        $seriesId = (int) DB::table('finance_document_series')->where('uuid', $quote->id->uuid)->value('id');
        $revision = $this->publishFixture($owner, $seriesId);
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->delete();

        return compact('quote', 'revision');
    }
}

final class ApiQuoteMailer implements QuoteMailer
{
    public int $dispatches = 0;

    public int $configuredChecks = 0;

    public bool $configured = true;

    public function assertConfigured(int $ownerId): void
    {
        $this->configuredChecks++;
        if (! $this->configured) {
            throw new InvalidQuoteAction('smtp_not_configured');
        }
    }

    public function assertRevisionReady(QuoteRevisionRef $revision): void {}

    public function dispatch(int $ownerId, int $deliveryId): void
    {
        $this->dispatches++;
    }
}

final class ApiDocumentRenderer implements DocumentRenderer
{
    public function render(array $snapshot): string
    {
        return "%PDF-1.4\n% quote-api-test\n";
    }
}

final class ApiQuoteToInvoicePort implements QuoteToInvoicePort
{
    public function createDraft(int $ownerId, QuoteRevisionRef $source, array $immutableSnapshot): InvoiceDraftTarget
    {
        return new InvoiceDraftTarget('invoice-draft:4242', null);
    }
}
