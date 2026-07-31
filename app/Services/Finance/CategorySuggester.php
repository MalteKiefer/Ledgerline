<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\FinancePartner;

/**
 * Read-only merchant->category suggestions — the server port of the client's
 * merchant-learn.js. The business-partner list doubles as the rule store: a
 * partner's `category` is the category the owner has taught for that merchant.
 * This only SUGGESTS (never writes a category onto a transaction); the client
 * decides whether to apply. Owner-scoped via the models' global scopes.
 */
class CategorySuggester
{
    /** Normalise a merchant name (drop legal forms + punctuation) — mirrors normMerchant(). */
    public function normMerchant(?string $s): string
    {
        $s = mb_strtolower((string) $s);
        $s = preg_replace('/\b(gmbh|mbh|ug|ag|kg|ohg|gbr|e\.?k\.?|co\.?|deutschland|ltd|limited|llc|inc|international|distribution)\b/', ' ', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;

        return trim($s);
    }

    /** The taught category for a merchant name, or '' — mirrors learnedCategoryFor(). */
    public function learnedCategoryFor(string $name): string
    {
        $nk = $this->normMerchant($name);
        if (mb_strlen($nk) < 2) {
            return '';
        }
        foreach (FinancePartner::query()->get() as $partner) {
            if ($this->normMerchant($partner->name) === $nk) {
                $cat = is_string($partner->category ?? null) ? $partner->category : '';

                return $cat;
            }
        }

        return '';
    }

    /**
     * Suggestions for the owner's transactions that have no category yet.
     * Read-only: returns [{tx_id, merchant, suggested_category}] for txs where a
     * learned category exists; never mutates a row.
     *
     * @return list<array{tx_id: int, merchant: string, suggested_category: string}>
     */
    public function suggestions(): array
    {
        $out = [];
        $tx = BankTransaction::query()
            ->where(fn ($q) => $q->whereNull('vat_cat')->orWhere('vat_cat', ''))
            ->get();
        foreach ($tx as $row) {
            $merchant = (string) ($row->counterparty ?: '');
            if ($merchant === '') {
                continue;
            }
            $cat = $this->learnedCategoryFor($merchant);
            if ($cat !== '') {
                $out[] = ['tx_id' => (int) $row->id, 'merchant' => $merchant, 'suggested_category' => $cat];
            }
        }

        return $out;
    }
}
