<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Converts a `default_rate` input in major units (e.g. euros/hour) into the
 * stored `default_rate_cents` column. Shared by the customer and project
 * requests.
 */
trait ResolvesDefaultRate
{
    protected function mergeDefaultRate(): void
    {
        $this->merge([
            'default_rate_cents' => $this->filled('default_rate')
                ? (int) round(((float) $this->input('default_rate')) * 100)
                : null,
        ]);
    }
}
