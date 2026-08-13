<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Secret fields whose form input is left blank should keep the stored value
 * rather than overwriting it with an empty string (the form never renders the
 * current secret back, so a blank submission means "leave it unchanged").
 */
final class KeepBlankSecrets
{
    /**
     * Drop any of $keys whose value in $data is empty/blank, so a blank
     * submission preserves the stored secret instead of wiping it.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function preserve(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (empty($data[$key])) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
