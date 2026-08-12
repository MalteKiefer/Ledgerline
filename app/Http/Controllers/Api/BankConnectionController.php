<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\Finance\GoCardlessClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Direct bank account retrieval via GoCardless Bank Account Data (PSD2/XS2A).
 * Owner-scoped, module:finance-gated, opt-in — complements the CSV/MT940 import.
 * The connect flow: pick a bank -> create a consent (requisition) -> the user
 * authenticates at their bank via the returned link -> finalize resolves the
 * linked account -> sync pulls booked transactions into bank_transactions
 * (sig-deduplicated, attributed to a chosen payment method).
 */
class BankConnectionController extends Controller
{
    public function __construct(private readonly GoCardlessClient $gc) {}

    /** Connection list + whether GoCardless is configured for the workspace. */
    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json([
            'configured' => GoCardlessClient::configured(),
            'connections' => BankConnection::query()->orderByDesc('id')->get()
                ->map(fn (BankConnection $c): array => $this->present($c))->all(),
        ]);
    }

    /** List banks for a country (e.g. "de"). */
    public function institutions(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $request->validate(['country' => ['required', 'string', 'size:2', 'alpha']]);
        if (! GoCardlessClient::configured()) {
            return response()->json(['error' => 'not_configured'], 422);
        }
        try {
            return response()->json(['institutions' => $this->gc->institutions($request->string('country')->value())]);
        } catch (Throwable) {
            return response()->json(['error' => 'upstream_failed'], 502);
        }
    }

    /** Create a consent for a bank; returns the SCA link the user must open. */
    public function connect(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'institution_id' => ['required', 'string', 'max:128'],
            'institution_name' => ['nullable', 'string', 'max:191'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'redirect' => ['required', 'url', 'max:255'],
        ]);
        if (! GoCardlessClient::configured()) {
            return response()->json(['error' => 'not_configured'], 422);
        }
        $reference = 'll-'.Str::random(24);
        try {
            $req = $this->gc->createRequisition($request->string('institution_id')->value(), $reference, $request->string('redirect')->value());
        } catch (Throwable) {
            return response()->json(['error' => 'upstream_failed'], 502);
        }

        $conn = new BankConnection;
        $conn->forceFill([
            'user_id' => $uid,
            'payment_method_id' => $request->filled('payment_method_id') ? $request->integer('payment_method_id') : null,
            'provider' => 'gocardless',
            'institution_id' => $request->string('institution_id')->value(),
            'institution_name' => $request->filled('institution_name') ? $request->string('institution_name')->value() : null,
            'requisition_id' => $req['id'],
            'reference' => $reference,
            'status' => 'created',
        ])->save();

        return response()->json(['id' => $conn->id, 'link' => $req['link']], 201);
    }

    /** Resolve the requisition after the user returned from the bank. */
    public function finalize(Request $request, BankConnection $bankConnection): JsonResponse
    {
        $this->requireUser($request);
        if (! is_string($bankConnection->requisition_id) || $bankConnection->requisition_id === '') {
            return response()->json(['error' => 'no_requisition'], 422);
        }
        try {
            $r = $this->gc->requisition($bankConnection->requisition_id);
        } catch (Throwable) {
            return response()->json(['error' => 'upstream_failed'], 502);
        }
        $account = $r['accounts'][0] ?? null;
        $bankConnection->forceFill([
            'account_id' => $account,
            'status' => $account !== null ? 'linked' : 'created',
            // GoCardless consents last 90 days by default.
            'consent_expires_at' => $account !== null ? now()->addDays(90) : null,
        ])->save();

        return response()->json($this->present($bankConnection->refresh()));
    }

    /** Pull booked transactions for a linked connection into bank_transactions. */
    public function sync(Request $request, BankConnection $bankConnection): JsonResponse
    {
        $this->requireUser($request);
        if ($bankConnection->status !== 'linked' || ! is_string($bankConnection->account_id) || $bankConnection->account_id === '') {
            return response()->json(['error' => 'not_linked'], 422);
        }
        try {
            $rows = $this->gc->transactions($bankConnection->account_id);
        } catch (Throwable) {
            return response()->json(['error' => 'upstream_failed'], 502);
        }

        $imported = DB::transaction(function () use ($rows, $bankConnection): int {
            // Existing signatures for this user (BankTransaction is owner-scoped).
            $seen = [];
            foreach (BankTransaction::query()->whereNotNull('sig')->pluck('sig') as $s) {
                if (is_string($s)) {
                    $seen[$s] = true;
                }
            }
            $count = 0;
            foreach ($rows as $row) {
                if (isset($seen[$row['sig']])) {
                    continue;
                }
                $tx = new BankTransaction;
                $tx->fill([
                    'payment_method_id' => $bankConnection->payment_method_id,
                    'date' => $row['date'],
                    'amount' => $row['amount'],
                    'sig' => $row['sig'],
                    'counterparty' => $row['counterparty'],
                    'counterparty_iban' => $row['counterparty_iban'],
                    'purpose' => $row['purpose'],
                ]);
                $tx->save();
                $seen[$row['sig']] = true;
                $count++;
            }

            return $count;
        });

        $bankConnection->forceFill(['last_synced_at' => now()])->save();

        return response()->json(['ok' => true, 'imported' => $imported]);
    }

    public function destroy(Request $request, BankConnection $bankConnection): JsonResponse
    {
        $this->requireUser($request);
        $bankConnection->delete();

        return response()->json([], 204);
    }

    /** @return array<string,mixed> */
    private function present(BankConnection $c): array
    {
        return [
            'id' => $c->id,
            'institution_id' => $c->institution_id,
            'institution_name' => $c->institution_name,
            'payment_method_id' => $c->payment_method_id,
            'status' => $c->status,
            'consent_expires_at' => $c->consent_expires_at?->toIso8601String(),
            'last_synced_at' => $c->last_synced_at?->toIso8601String(),
        ];
    }

    /** Admin: read/set the workspace GoCardless credentials (never returned). */
    public function showCredentials(Request $request): JsonResponse
    {
        return response()->json(['configured' => GoCardlessClient::configured()]);
    }

    public function updateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'secret_id' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);
        $s = AppSettings::current();
        // Blank-preserve: an empty field keeps the stored secret (never wiped by a save).
        $patch = [];
        if ($request->filled('secret_id')) {
            $patch['gocardless_secret_id'] = $request->string('secret_id')->value();
        }
        if ($request->filled('secret_key')) {
            $patch['gocardless_secret_key'] = $request->string('secret_key')->value();
        }
        if ($patch !== []) {
            $s->forceFill($patch)->save();
        }

        return response()->json(['ok' => true, 'configured' => GoCardlessClient::configured()]);
    }
}
