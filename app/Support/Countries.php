<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Intl\Countries as IntlCountries;

/**
 * Helper around the offline ISO 3166-1 country list provided by symfony/intl.
 *
 * Countries are stored by their alpha-2 code. Flags are rendered as Unicode
 * regional-indicator emoji derived from the code, so no images or external
 * resources are needed.
 */
final class Countries
{
    /**
     * All countries as value/label/flag rows, sorted by name.
     *
     * @return array<int, array{value: string, label: string, flag: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (IntlCountries::getNames('en') as $code => $name) {
            $options[] = [
                'value' => $code,
                'label' => $name,
                'flag' => self::flag($code),
            ];
        }

        return $options;
    }

    /**
     * The display name for a country code, or null when unknown/empty.
     */
    public static function name(?string $code): ?string
    {
        if ($code === null || $code === '' || ! IntlCountries::exists($code)) {
            return null;
        }

        return IntlCountries::getName($code, 'en');
    }

    /**
     * The flag emoji for an alpha-2 country code (empty string when invalid).
     */
    public static function flag(string $code): string
    {
        if (! IntlCountries::exists($code)) {
            return '';
        }

        $base = 0x1F1E6; // Regional Indicator Symbol Letter A
        $offset = ord('A');

        return mb_chr($base + (ord($code[0]) - $offset))
            .mb_chr($base + (ord($code[1]) - $offset));
    }

    /**
     * Whether the given code is a valid ISO 3166-1 alpha-2 country code.
     */
    public static function exists(string $code): bool
    {
        return IntlCountries::exists($code);
    }

    /**
     * All valid alpha-2 country codes.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return IntlCountries::getCountryCodes();
    }
}
