<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Settings;

use App\Models\UserSetting;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;

final class EloquentQuoteSettings implements QuoteSettings
{
    public function quoteNumberFormat(int $ownerId): string
    {
        $value = $this->value($ownerId, 'quote_number_format');

        return is_string($value) && trim($value) !== '' ? $value : 'AN-YYYY-NNNN';
    }

    public function quoteNumberFloor(int $ownerId): int
    {
        return $this->positiveInteger(
            $this->value($ownerId, 'quote_next_number'),
            1,
        );
    }

    public function defaultValidityDays(int $ownerId): int
    {
        return $this->positiveInteger(
            $this->value($ownerId, 'quote_valid_days'),
            30,
        );
    }

    public function invoicePaymentTermsDays(int $ownerId): int
    {
        $value = $this->value($ownerId, 'invoice_payment_terms_days');

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : 14;
    }

    public function ownerTimezone(int $ownerId): string
    {
        $value = $this->value($ownerId, 'timezone');

        return is_string($value) && trim($value) !== '' ? $value : 'UTC';
    }

    public function senderIdentity(int $ownerId): array
    {
        $settings = UserSetting::query()->find($ownerId);
        $name = $settings?->getAttribute('company_smtp_from_name')
            ?? $settings?->getAttribute('company_name');
        $address = $settings?->getAttribute('company_smtp_from_address')
            ?? $settings?->getAttribute('company_email');

        return [
            'name' => is_string($name) ? $name : '',
            'address' => is_string($address) ? $address : '',
        ];
    }

    private function positiveInteger(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private function value(int $ownerId, string $attribute): mixed
    {
        return UserSetting::query()->find($ownerId)?->getAttribute($attribute);
    }
}
