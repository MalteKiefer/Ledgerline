<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AppSettings;
use App\Support\OutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Thin client for GoCardless Bank Account Data (ex-Nordigen, PSD2/XS2A).
 *
 * The API host is FIXED (bankaccountdata.gocardless.com) — never user input — but
 * every call still goes through OutboundUrl::client (IP-pinned, redirect-free,
 * link-local/metadata-blocked) as a matter of policy. Credentials come from the
 * encrypted AppSettings columns; the short-lived access token is cached briefly
 * (never persisted). All methods throw RuntimeException on failure.
 */
class GoCardlessClient
{
    private const BASE = 'https://bankaccountdata.gocardless.com/api/v2';

    private const TOKEN_CACHE = 'finance.gocardless.access_token';

    /** True when the workspace has configured GoCardless credentials. */
    public static function configured(): bool
    {
        $s = AppSettings::current();

        return is_string($s->gocardless_secret_id) && $s->gocardless_secret_id !== ''
            && is_string($s->gocardless_secret_key) && $s->gocardless_secret_key !== '';
    }

    /** Obtain (and cache for ~23h) an access token from the workspace secret pair. */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $s = AppSettings::current();
        $id = is_string($s->gocardless_secret_id) ? $s->gocardless_secret_id : '';
        $key = is_string($s->gocardless_secret_key) ? $s->gocardless_secret_key : '';
        if ($id === '' || $key === '') {
            throw new RuntimeException('GoCardless is not configured.');
        }
        $res = OutboundUrl::client(self::BASE.'/token/new/', 20)
            ->acceptJson()->asJson()
            ->post(self::BASE.'/token/new/', ['secret_id' => $id, 'secret_key' => $key]);
        if (! $res->successful() || ! is_string($res->json('access'))) {
            throw new RuntimeException('GoCardless token request failed.');
        }
        $token = (string) $res->json('access');
        // Access tokens are valid ~24h; refresh a little early.
        Cache::put(self::TOKEN_CACHE, $token, now()->addHours(23));

        return $token;
    }

    private function auth(): PendingRequest
    {
        return OutboundUrl::client(self::BASE, 30)
            ->withToken($this->accessToken())->acceptJson();
    }

    /**
     * List banks for a 2-letter country code.
     *
     * @return list<array{id:string,name:string,bic:?string,logo:?string}>
     */
    public function institutions(string $country): array
    {
        $res = $this->auth()->get(self::BASE.'/institutions/', ['country' => strtolower($country)]);
        if (! $res->successful()) {
            throw new RuntimeException('GoCardless institutions request failed.');
        }
        $out = [];
        foreach ((array) $res->json() as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null)) {
                continue;
            }
            $out[] = [
                'id' => $row['id'],
                'name' => is_string($row['name'] ?? null) ? $row['name'] : $row['id'],
                'bic' => is_string($row['bic'] ?? null) ? $row['bic'] : null,
                'logo' => is_string($row['logo'] ?? null) ? $row['logo'] : null,
            ];
        }

        return $out;
    }

    /**
     * Create a requisition (consent). Returns [requisitionId, link, expiresDays].
     *
     * @return array{id:string,link:string}
     */
    public function createRequisition(string $institutionId, string $reference, string $redirect): array
    {
        $res = $this->auth()->asJson()->post(self::BASE.'/requisitions/', [
            'institution_id' => $institutionId,
            'reference' => $reference,
            'redirect' => $redirect,
            'user_language' => 'DE',
        ]);
        if (! $res->successful() || ! is_string($res->json('id')) || ! is_string($res->json('link'))) {
            throw new RuntimeException('GoCardless requisition creation failed.');
        }

        return ['id' => (string) $res->json('id'), 'link' => (string) $res->json('link')];
    }

    /**
     * Fetch a requisition — its status + linked account ids.
     *
     * @return array{status:string,accounts:list<string>}
     */
    public function requisition(string $id): array
    {
        $res = $this->auth()->get(self::BASE.'/requisitions/'.rawurlencode($id).'/');
        if (! $res->successful()) {
            throw new RuntimeException('GoCardless requisition fetch failed.');
        }
        $accounts = [];
        foreach ((array) $res->json('accounts') as $a) {
            if (is_string($a) && $a !== '') {
                $accounts[] = $a;
            }
        }

        return ['status' => is_string($res->json('status')) ? (string) $res->json('status') : '', 'accounts' => $accounts];
    }

    /**
     * Booked transactions for one account, normalized to the app's tx shape.
     *
     * @return list<array{date:string,amount:string,counterparty:?string,counterparty_iban:?string,purpose:?string,sig:string}>
     */
    public function transactions(string $accountId): array
    {
        $res = $this->auth()->get(self::BASE.'/accounts/'.rawurlencode($accountId).'/transactions/');
        if (! $res->successful()) {
            throw new RuntimeException('GoCardless transactions fetch failed.');
        }
        $booked = $res->json('transactions.booked');
        $rows = is_array($booked) ? $booked : [];
        $out = [];
        foreach ($rows as $t) {
            if (! is_array($t)) {
                continue;
            }
            $mapped = $this->mapTransaction($t);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @param  array<mixed,mixed>  $t
     * @return array{date:string,amount:string,counterparty:?string,counterparty_iban:?string,purpose:?string,sig:string}|null
     */
    private function mapTransaction(array $t): ?array
    {
        $amt = $t['transactionAmount'] ?? null;
        $amount = is_array($amt) && is_scalar($amt['amount'] ?? null) ? (string) $amt['amount'] : '';
        $date = $this->str($t['bookingDate'] ?? $t['valueDate'] ?? null);
        if ($amount === '' || $date === '') {
            return null;
        }
        $counterparty = $this->str($t['creditorName'] ?? $t['debtorName'] ?? null) ?: null;
        $iban = null;
        foreach (['creditorAccount', 'debtorAccount'] as $k) {
            $acc = $t[$k] ?? null;
            if (is_array($acc) && is_string($acc['iban'] ?? null)) {
                $iban = $acc['iban'];
                break;
            }
        }
        $purpose = $this->str(
            is_array($t['remittanceInformationUnstructuredArray'] ?? null)
                ? implode(' ', array_map(fn ($x): string => is_string($x) ? $x : '', $t['remittanceInformationUnstructuredArray']))
                : ($t['remittanceInformationUnstructured'] ?? null)
        ) ?: null;

        // Stable dedup signature: prefer the provider's transactionId, else a
        // deterministic hash of the immutable fields.
        $txId = $this->str($t['transactionId'] ?? $t['internalTransactionId'] ?? null);
        $sig = $txId !== ''
            ? 'gc:'.substr(hash('sha256', $txId), 0, 40)
            : 'gc:'.substr(hash('sha256', $date.'|'.$amount.'|'.($counterparty ?? '').'|'.($purpose ?? '')), 0, 40);

        return [
            'date' => substr($date, 0, 10),
            'amount' => $amount,
            'counterparty' => $counterparty,
            'counterparty_iban' => $iban,
            'purpose' => $purpose,
            'sig' => $sig,
        ];
    }

    private function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** Best-effort: is GoCardless reachable + our credentials valid? */
    public function ping(): bool
    {
        try {
            $this->accessToken();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
