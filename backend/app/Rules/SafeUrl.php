<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\OutboundUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a user-supplied URL is a safe outbound target: an http(s) URL
 * whose host does not resolve to a link-local / cloud-metadata address. See
 * {@see OutboundUrl}.
 */
final class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // let 'nullable'/'required' handle emptiness
        }

        if (! OutboundUrl::safe($value)) {
            $fail('settings.safe_url')->translate(['attribute' => $attribute]);
        }
    }
}
