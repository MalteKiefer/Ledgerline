<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Database\Factories\IncomeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual income entry (money in) not derived from time. Global (not
 * team-scoped). Amounts are integer cents.
 */
#[Fillable(['date', 'description', 'amount_cents', 'currency', 'billed'])]
class IncomeEntry extends Model
{
    /** @use HasFactory<IncomeEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount_cents' => 'integer',
            'billed' => 'boolean',
        ];
    }

    public function amount(): Money
    {
        return new Money($this->amount_cents, $this->currency);
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
}
