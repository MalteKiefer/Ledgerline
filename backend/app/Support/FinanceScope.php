<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BankTransaction;
use App\Models\FinanceReceipt;
use App\Models\PaymentMethod;

/**
 * Whether a finance row belongs to the business or to private life.
 *
 * One place owns the rule, because it decides what reaches a tax report and a
 * second copy of it would eventually disagree with this one.
 *
 * The rule: an account states its own scope and always has one. A booking or a
 * receipt may state one, and when it does that wins; when it does not, it takes
 * the scope of the account it was paid from. That way opening a private account
 * costs nothing on the rows already there, and a private purchase from the
 * business card is a single field on that one booking.
 *
 * Not to be confused with `vat_cat = 'private'`, which marks an owner
 * withdrawal or deposit ON a business account: that stays in the books and is
 * only kept out of VAT and expenses. A private-scope row is outside the books.
 */
final class FinanceScope
{
    public const BUSINESS = 'business';

    public const PRIVATE = 'private';

    /** @var list<string> */
    public const ALL = [self::BUSINESS, self::PRIVATE];

    /** Normalise anything to a known scope; unknown input reads as business. */
    public static function normalise(mixed $value): string
    {
        return is_string($value) && $value === self::PRIVATE ? self::PRIVATE : self::BUSINESS;
    }

    public static function ofAccount(?PaymentMethod $account): string
    {
        return self::normalise($account?->scope);
    }

    public static function ofTransaction(BankTransaction $tx): string
    {
        if (is_string($tx->scope) && $tx->scope !== '') {
            return self::normalise($tx->scope);
        }

        // The account is the fallback, so an unmarked booking follows its account.
        return self::ofAccount($tx->paymentMethod);
    }

    public static function ofReceipt(FinanceReceipt $receipt): string
    {
        if (is_string($receipt->scope) && $receipt->scope !== '') {
            return self::normalise($receipt->scope);
        }
        $tx = $receipt->bankTransaction;

        // A standalone receipt (no booking yet) is business — that is what the
        // overwhelming majority of uploads are, and it can be overridden.
        return $tx instanceof BankTransaction ? self::ofTransaction($tx) : self::BUSINESS;
    }

    public static function isBusiness(string $scope): bool
    {
        return self::normalise($scope) === self::BUSINESS;
    }
}
