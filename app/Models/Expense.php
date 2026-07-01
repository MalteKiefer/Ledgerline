<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A company expense (money out). Global (not team-scoped). Amounts are integer
 * cents; amount_cents is gross, tax_cents the derived VAT portion.
 */
#[Fillable([
    'date',
    'description',
    'vendor',
    'category',
    'category_custom',
    'amount_cents',
    'currency',
    'tax_rate',
    'tax_cents',
    'payment_status',
    'paid_on',
    'billable',
    'billed',
    'labels',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'paid_on' => 'date',
            'category' => ExpenseCategory::class,
            'payment_status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'tax_cents' => 'integer',
            'tax_rate' => 'integer',
            'billable' => 'boolean',
            'billed' => 'boolean',
            'labels' => 'array',
        ];
    }

    /**
     * Gross total (what was paid).
     */
    public function gross(): Money
    {
        return new Money($this->amount_cents, $this->currency);
    }

    /**
     * VAT portion.
     */
    public function tax(): Money
    {
        return new Money($this->tax_cents, $this->currency);
    }

    /**
     * Net total (gross minus VAT).
     */
    public function net(): Money
    {
        return new Money($this->amount_cents - $this->tax_cents, $this->currency);
    }

    /**
     * The category to display (custom overrides the preset).
     */
    public function categoryLabel(): string
    {
        return filled($this->category_custom) ? $this->category_custom : $this->category->label();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}
