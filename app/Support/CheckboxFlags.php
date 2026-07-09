<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalise HTML checkbox fields into booleans on a validated-data array. An
 * unchecked box submits no field at all, so "absent" must be folded to false —
 * otherwise a toggle can only ever be turned on. Mirrors KeepBlankSecrets in
 * shape: a small, explicit helper the settings controllers share.
 */
final class CheckboxFlags
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $flags
     * @return array<string, mixed>
     */
    public static function apply(array $data, Request $request, array $flags): array
    {
        foreach ($flags as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
