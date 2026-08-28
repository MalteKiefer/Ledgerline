<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\FinanceStockMovement;
use App\Models\User;
use App\Services\Finance\StockLedger;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_article_is_created_and_appears_in_the_snapshot(): void
    {
        $this->signIn();

        $this->postJson(route('api.finance.products.store'), [
            'kind' => 'hardware',
            'sku' => 'SW-24',
            'name' => '24-Port Switch',
            'unit' => 'Stück',
            'price_net' => 249.9,
            'purchase_price' => 180,
            'vat_rate' => 19,
            'track_stock' => true,
        ])->assertCreated()->assertJsonPath('product.name', '24-Port Switch');

        $this->getJson(route('api.finance.data'))
            ->assertOk()
            ->assertJsonPath('products.0.sku', 'SW-24')
            // Stock starts at zero and only a movement changes it.
            ->assertJsonPath('products.0.stock_qty', '0.0000');
    }

    public function test_stock_moves_only_through_a_movement_and_the_ledger_explains_the_figure(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);

        // Two deliveries and one sale.
        $this->postJson(route('api.finance.products.stock', $product), ['qty' => 10, 'reason' => 'purchase'])->assertCreated();
        $this->postJson(route('api.finance.products.stock', $product), ['qty' => 5, 'reason' => 'purchase'])->assertCreated();
        $this->postJson(route('api.finance.products.stock', $product), ['qty' => -3, 'reason' => 'sale'])
            ->assertCreated()
            ->assertJsonPath('product.stock_qty', '12.0000');

        // The figure equals the ledger, which is the point of keeping both.
        $this->assertSame('12.0000', (string) $product->fresh()?->stock_qty);
        $this->assertSame(3, FinanceStockMovement::query()->count());

        // A zero movement says nothing and is refused.
        $this->postJson(route('api.finance.products.stock', $product), ['qty' => 0])->assertStatus(422);
    }

    public function test_stock_endpoint_preserves_scale_four_strings_without_accepting_alternate_decimal_syntax(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);

        $this->postJson(route('api.finance.products.stock', $product), [
            'qty' => '0.0001',
            'reason' => 'purchase',
        ])->assertCreated();
        $this->assertSame('0.0001', (string) FinanceStockMovement::query()->sole()->qty);
        $this->assertSame('0.0001', (string) $product->fresh()?->stock_qty);

        foreach (['1.23456', '1,2345', '1e-4'] as $invalidQuantity) {
            $this->postJson(route('api.finance.products.stock', $product), [
                'qty' => $invalidQuantity,
                'reason' => 'purchase',
            ])->assertUnprocessable()->assertJsonValidationErrors('qty');
        }

        $this->assertSame(1, FinanceStockMovement::query()->count());
        $this->assertSame('0.0001', (string) $product->fresh()?->stock_qty);
    }

    public function test_the_update_path_cannot_set_the_stock_figure(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        StockLedger::move($product, 7, 'initial');

        // Someone sending a stock figure gets their other fields updated and the
        // figure ignored — otherwise the ledger would stop explaining it.
        $this->putJson(route('api.finance.products.update', $product), [
            'name' => 'Renamed',
            'stock_qty' => 999,
            'stock_min' => 42,
        ])->assertOk()->assertJsonPath('product.name', 'Renamed');

        $fresh = $product->fresh();
        $this->assertSame('7.0000', (string) $fresh?->stock_qty);
        $this->assertNull($fresh?->stock_min);
    }

    public function test_an_untracked_article_records_movements_without_carrying_a_figure(): void
    {
        // Switching tracking on later should find a history rather than nothing,
        // but an article nobody counts has no meaningful count.
        $this->signIn();
        $product = $this->article(['track_stock' => false]);

        StockLedger::move($product, 4, 'purchase');

        $this->assertSame(1, FinanceStockMovement::query()->count());
        $this->assertSame('0.0000', (string) $product->fresh()?->stock_qty);
    }

    public function test_the_reorder_level_only_warns_for_something_we_count(): void
    {
        $this->signIn();

        $counted = $this->article(['track_stock' => true]);
        $counted->forceFill(['stock_qty' => 2, 'stock_min' => 5])->save();
        $this->assertTrue($counted->fresh()?->isLowOnStock());

        $uncounted = $this->article(['track_stock' => false]);
        $uncounted->forceFill(['stock_qty' => 0, 'stock_min' => 5])->save();
        $this->assertFalse($uncounted->fresh()?->isLowOnStock());

        $noLevel = $this->article(['track_stock' => true]);
        $this->assertFalse($noLevel->fresh()?->isLowOnStock());
    }

    public function test_recompute_lets_the_ledger_win_over_a_drifted_figure(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        StockLedger::move($product, 6, 'purchase');
        StockLedger::move($product, -1, 'sale');

        // Pretend the denormalised figure drifted.
        $product->fresh()?->forceFill(['stock_qty' => 100])->save();

        $this->assertSame('5.0000', StockLedger::recompute($product));
        $this->assertSame('5.0000', (string) $product->fresh()?->stock_qty);
    }

    public function test_stock_ledger_moves_large_scale_four_quantities_exactly(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        $product->forceFill(['stock_qty' => '999999999999.9997'])->save();

        $movement = StockLedger::move($product, '0.0001', 'purchase');

        $this->assertSame('0.0001', (string) $movement->qty);
        $this->assertSame('999999999999.9998', (string) $product->fresh()?->stock_qty);
    }

    public function test_stock_ledger_moves_negative_scale_four_quantities_exactly(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        $product->forceFill(['stock_qty' => '-999999999999.9997'])->save();

        $movement = StockLedger::move($product, '-0.0001', 'sale');

        $this->assertSame('-0.0001', (string) $movement->qty);
        $this->assertSame('-999999999999.9998', (string) $product->fresh()?->stock_qty);
    }

    public function test_recompute_uses_exact_checked_scale_four_arithmetic(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        FinanceStockMovement::query()->create([
            'finance_product_id' => $product->id,
            'qty' => '999999999999.9997',
            'reason' => 'initial',
            'occurred_at' => now(),
        ]);
        FinanceStockMovement::query()->create([
            'finance_product_id' => $product->id,
            'qty' => '0.0001',
            'reason' => 'purchase',
            'occurred_at' => now(),
        ]);

        $this->assertSame('999999999999.9998', StockLedger::recompute($product));
        $this->assertSame('999999999999.9998', (string) $product->fresh()?->stock_qty);
    }

    public function test_stock_move_rejects_storage_overflow_atomically(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        $product->forceFill(['stock_qty' => '999999999999.9999'])->save();

        try {
            StockLedger::move($product, '0.0001', 'purchase');
            $this->fail('An overflowing stock movement was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('stock_quantity_overflow', $exception->getMessage());
        }

        $this->assertSame('999999999999.9999', (string) $product->fresh()?->stock_qty);
        $this->assertSame(0, FinanceStockMovement::query()->count());
    }

    public function test_recompute_rejects_storage_overflow_without_rewriting_stock(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        $product->forceFill(['stock_qty' => '7.0000'])->save();
        foreach (['999999999999.9999', '0.0001'] as $quantity) {
            FinanceStockMovement::query()->create([
                'finance_product_id' => $product->id,
                'qty' => $quantity,
                'reason' => 'correction',
                'occurred_at' => now(),
            ]);
        }

        try {
            StockLedger::recompute($product);
            $this->fail('An overflowing ledger sum was written to stock.');
        } catch (DomainException $exception) {
            $this->assertSame('stock_quantity_overflow', $exception->getMessage());
        }

        $this->assertSame('7.0000', (string) $product->fresh()?->stock_qty);
    }

    public function test_reorder_comparison_does_not_collapse_large_scale_four_values(): void
    {
        $this->signIn();
        $product = $this->article(['track_stock' => true]);
        $product->forceFill([
            'stock_qty' => '999999999999.9998',
            'stock_min' => '999999999999.9997',
        ])->save();

        $this->assertFalse($product->fresh()?->isLowOnStock());

        $product->forceFill([
            'stock_qty' => '999999999999.9997',
            'stock_min' => '999999999999.9998',
        ])->save();
        $this->assertTrue($product->fresh()?->isLowOnStock());
    }

    public function test_another_owner_cannot_reach_the_article_or_its_movements(): void
    {
        $mine = $this->signIn();
        $product = $this->article(['track_stock' => true]);
        StockLedger::move($product, 1, 'purchase');

        $other = User::factory()->create();
        // Sanctum caches the resolved token, so the guard has to be forgotten
        // before a second actor can be believed.
        app('auth')->forgetGuards();
        $this->signIn($other);

        $this->putJson(route('api.finance.products.update', $product), ['name' => 'Stolen'])->assertNotFound();
        $this->postJson(route('api.finance.products.stock', $product), ['qty' => 5])->assertNotFound();
        $this->getJson(route('api.finance.products.movements', $product))->assertNotFound();
        $this->deleteJson(route('api.finance.products.destroy', $product))->assertNotFound();

        $this->assertSame('24-Port Switch', (string) $product->fresh()?->name);
        $this->assertSame($mine->id, (int) $product->fresh()?->user_id);
    }

    public function test_a_stale_version_is_refused_instead_of_overwriting(): void
    {
        $this->signIn();
        $product = $this->article([]);

        $this->putJson(route('api.finance.products.update', $product), ['name' => 'First', 'version' => 0])->assertOk();
        $this->putJson(route('api.finance.products.update', $product), ['name' => 'Second', 'version' => 0])
            ->assertStatus(409)
            ->assertJsonPath('error', 'version_conflict')
            ->assertJsonPath('version', 1);

        $this->assertSame('First', (string) $product->fresh()?->name);
    }

    public function test_a_supplier_from_another_owner_is_refused(): void
    {
        $this->signIn();
        $foreign = new FinancePartner;
        $foreign->forceFill(['user_id' => User::factory()->create()->id, 'name' => 'Not mine'])->save();

        $this->postJson(route('api.finance.products.store'), [
            'kind' => 'hardware',
            'name' => 'Cable',
            'supplier_id' => $foreign->id,
        ])->assertStatus(422);
    }

    public function test_an_article_number_is_unique_among_the_live_ones(): void
    {
        $this->signIn();
        $this->postJson(route('api.finance.products.store'), ['kind' => 'hardware', 'name' => 'A', 'sku' => 'X-1'])->assertCreated();

        // Deleting frees the number again; a bin entry must not block it.
        $first = FinanceProduct::query()->where('sku', 'X-1')->firstOrFail();
        $this->deleteJson(route('api.finance.products.destroy', $first))->assertOk();

        $this->postJson(route('api.finance.products.store'), ['kind' => 'hardware', 'name' => 'B', 'sku' => 'X-1'])->assertCreated();
    }

    /** @param array<string, mixed> $attrs */
    private function article(array $attrs): FinanceProduct
    {
        $product = new FinanceProduct;
        $product->fill(array_merge([
            'kind' => 'hardware',
            'name' => '24-Port Switch',
            'sku' => null,
            'price_net' => 249.9,
        ], $attrs));
        $product->save();

        return $product;
    }
}
