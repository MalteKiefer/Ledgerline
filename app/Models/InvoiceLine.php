<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single invoice line. Unit prices are net (cents); line_net_cents and
 * line_tax_cents are computed by the invoice calculator.
 */
#[Fillable([
    'position',
    'description',
    'quantity',
    'unit',
    'unit_price_cents',
    'tax_rate',
    'line_net_cents',
    'line_tax_cents',
])]
class InvoiceLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'tax_rate' => 'integer',
            'line_net_cents' => 'integer',
            'line_tax_cents' => 'integer',
        ];
    }

    public function unitPrice(): Money
    {
        return new Money($this->unit_price_cents, $this->invoice?->currency ?? 'EUR');
    }

    public function lineNet(): Money
    {
        return new Money($this->line_net_cents, $this->invoice?->currency ?? 'EUR');
    }

    public function lineTax(): Money
    {
        return new Money($this->line_tax_cents, $this->invoice?->currency ?? 'EUR');
    }

    /**
     * The localised unit label (falls back to the stored code).
     */
    public function unitLabel(?string $locale = null): string
    {
        return Unit::labelFor($this->unit, $locale);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
