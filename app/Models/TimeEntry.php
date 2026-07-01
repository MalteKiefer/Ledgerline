<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A billable time entry (money in). Global (not team-scoped). Duration is stored
 * in minutes and the resolved hourly rate in cents; the amount is derived.
 */
#[Fillable(['date', 'description', 'minutes', 'rate_cents', 'currency', 'billable', 'billed'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'minutes' => 'integer',
            'rate_cents' => 'integer',
            'billable' => 'boolean',
            'billed' => 'boolean',
        ];
    }

    /**
     * Duration in hours.
     */
    public function hours(): float
    {
        return $this->minutes / 60;
    }

    /**
     * The resolved hourly rate.
     */
    public function rate(): Money
    {
        return new Money($this->rate_cents, $this->currency);
    }

    /**
     * The billable amount (hours * rate).
     */
    public function amount(): Money
    {
        return new Money((int) round($this->minutes * $this->rate_cents / 60), $this->currency);
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
