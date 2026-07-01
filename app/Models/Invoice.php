<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TaxMode;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * An invoice or credit note. Global (not team-scoped). Amounts are integer
 * cents. Once finalised the record is immutable except for status and payment
 * fields (see the updating guard in booted()).
 */
#[Fillable([
    'type',
    'status',
    'customer_id',
    'issue_date',
    'due_date',
    'language',
    'currency',
    'tax_mode',
    'discount_cents',
    'intro_text',
    'closing_text',
    'payment_terms_days',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /** Fields that may still change after finalisation. */
    private const MUTABLE_AFTER_FINALIZE = ['status', 'paid_cents', 'paid_on', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('finalized_at') === null) {
                return;
            }

            if (array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_FINALIZE) !== []) {
                throw new RuntimeException('A finalised invoice cannot be modified.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_on' => 'date',
            'finalized_at' => 'datetime',
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'tax_mode' => TaxMode::class,
            'discount_cents' => 'integer',
            'net_cents' => 'integer',
            'tax_cents' => 'integer',
            'gross_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function net(): Money
    {
        return new Money($this->net_cents, $this->currency);
    }

    public function tax(): Money
    {
        return new Money($this->tax_cents, $this->currency);
    }

    public function gross(): Money
    {
        return new Money($this->gross_cents, $this->currency);
    }

    public function paid(): Money
    {
        return new Money($this->paid_cents, $this->currency);
    }

    public function outstanding(): Money
    {
        return new Money(max(0, $this->gross_cents - $this->paid_cents), $this->currency);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }
}
