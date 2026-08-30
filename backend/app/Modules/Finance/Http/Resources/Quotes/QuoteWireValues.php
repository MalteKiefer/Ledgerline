<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Quotes;

final class QuoteWireValues
{
    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $values
     * @return array<TKey, mixed>
     */
    public static function exactIntegerStrings(array $values): array
    {
        $serialized = $values;

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $serialized[$key] = self::exactIntegerStrings($value);

                continue;
            }

            $serialized[$key] = is_string($key) && is_int($value) && self::isExactIntegerField($key)
                ? (string) $value
                : $value;
        }

        return $serialized;
    }

    private static function isExactIntegerField(string $key): bool
    {
        return $key === 'minor'
            || $key === 'basis_points'
            || str_ends_with($key, '_minor')
            || str_ends_with($key, '_scaled')
            || str_ends_with($key, '_basis_points');
    }
}
