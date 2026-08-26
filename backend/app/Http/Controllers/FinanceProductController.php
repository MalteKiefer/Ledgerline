<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimisticUpdates;
use App\Models\FinanceProduct;
use App\Models\FinanceStockMovement;
use App\Services\Finance\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The article catalogue: what we sell, and how much of it is on the shelf.
 *
 * Its own controller rather than more of FinanceController, which already
 * carries invoices, transactions, receipts, partners, projects and categories.
 *
 * Stock deliberately has no field on the update path — it moves through
 * {@see stock()}, which writes a movement. A form that could set the figure
 * directly would leave a number nothing in the ledger explains.
 */
class FinanceProductController extends Controller
{
    use OptimisticUpdates;

    /** How many movements a single request will return. */
    private const MOVEMENT_LIMIT = 500;

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        return [
            'kind' => [$creating ? 'required' : 'sometimes', Rule::in(['service', 'hardware'])],
            'sku' => ['nullable', 'string', 'max:64'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['nullable', 'string', 'max:32'],
            'price_net' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
            'purchase_price' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', request()->user()?->id)],
            'category' => ['nullable', 'string', 'max:160'],
            'active' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
            'version' => ['nullable', 'integer'],
        ];
    }

    /**
     * The fields a request may set, with the ones it did not send left out —
     * so a partial update cannot blank a field by omission.
     *
     * @return array<string, mixed>
     */
    private function patch(Request $request): array
    {
        $patch = [];
        foreach (['kind', 'sku', 'name', 'description', 'unit', 'category', 'note'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $patch[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        // name is not nullable: an article without one cannot be picked.
        if (array_key_exists('name', $patch) && $patch['name'] === null) {
            unset($patch['name']);
        }
        foreach (['price_net', 'purchase_price', 'vat_rate'] as $field) {
            if ($request->has($field)) {
                $raw = $request->input($field);
                $patch[$field] = is_numeric($raw) ? (float) $raw : null;
            }
        }
        if ($request->has('supplier_id')) {
            $raw = $request->input('supplier_id');
            $patch['supplier_id'] = is_numeric($raw) ? (int) $raw : null;
        }
        foreach (['active', 'track_stock'] as $field) {
            if ($request->has($field)) {
                $patch[$field] = $request->boolean($field);
            }
        }

        return $patch;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules(true));

        $product = new FinanceProduct;
        $product->fill($this->patch($request));
        // price_net is not nullable in the column; an article with no price yet
        // is worth zero rather than broken.
        if ($product->price_net === null) {
            $product->setAttribute('price_net', 0);
        }
        $product->save();

        return response()->json(['product' => $product->fresh()], 201);
    }

    public function update(Request $request, FinanceProduct $product): JsonResponse
    {
        $request->validate($this->rules(false));

        $id = (int) $product->id;
        $result = $this->optimistic(
            FinanceProduct::class,
            $id,
            $this->patch($request),
            $request->has('version') ? $request->integer('version') : null,
        );

        return $this->optimisticJson($result, FinanceProduct::class, $id, 'product');
    }

    public function destroy(FinanceProduct $product): JsonResponse
    {
        $product->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $product = FinanceProduct::onlyTrashed()->findOrFail($id);
        $product->restore();

        return response()->json(['product' => $product->fresh()]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $product = FinanceProduct::onlyTrashed()->findOrFail($id);
        // The movements go with it (cascade). That is the one case where stock
        // history disappears, and it takes the article it described with it.
        $product->forceDelete();

        return response()->json(['ok' => true]);
    }

    /**
     * Book a stock movement.
     *
     * The quantity is signed by the caller rather than derived from the reason,
     * because a correction can go either way and guessing the direction from a
     * label would be wrong exactly when it matters.
     */
    public function stock(Request $request, FinanceProduct $product): JsonResponse
    {
        $request->validate([
            'qty' => ['required', 'numeric', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['nullable', Rule::in(FinanceStockMovement::REASONS)],
            'note' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $at = $request->input('occurred_at');
        $reason = $request->input('reason');
        $movement = StockLedger::move(
            $product,
            (float) $request->float('qty'),
            is_string($reason) && $reason !== '' ? $reason : 'correction',
            'manual',
            null,
            $request->string('note')->value() ?: null,
            is_string($at) && $at !== '' ? Carbon::parse($at) : null,
        );

        return response()->json([
            'movement' => $movement,
            'product' => $product->fresh(),
        ], 201);
    }

    /** The movement history of one article, newest first. */
    public function movements(FinanceProduct $product): JsonResponse
    {
        return response()->json([
            'movements' => FinanceStockMovement::query()
                ->where('finance_product_id', $product->getKey())
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(self::MOVEMENT_LIMIT)
                ->get(),
        ]);
    }
}
