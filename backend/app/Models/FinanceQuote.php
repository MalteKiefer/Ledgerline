<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A quote (Angebot).
 *
 * `number`/`seq`/`year` are NOT fillable: the server owns them, exactly as it
 * owns an invoice number, so a client cannot mint or reuse one.
 *
 * `lines` shares the invoice line shape — `{desc, qty, unit, unitPrice, vatRate}`
 * plus `productId` and `kind` — which is what makes the conversion to an invoice
 * a copy and keeps one totals implementation instead of two.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $number
 * @property int|null $seq
 * @property int|null $year
 * @property string $status draft|sent|accepted|declined
 * @property int|null $partner_id
 * @property array<string, mixed>|null $customer
 * @property string|null $title
 * @property Carbon|null $issue_date
 * @property Carbon|null $valid_until
 * @property string $currency
 * @property array<int, array<string, mixed>>|null $lines
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property string|null $net
 * @property string|null $vat
 * @property string|null $gross
 * @property string|null $intro_text
 * @property string|null $outro_text
 * @property string|null $note
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property int|null $converted_invoice_id
 * @property int|null $converted_project_id
 * @property string|null $pdf_path
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceQuote extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** The states a quote can be in. 'expired' is derived from the date, not stored. */
    public const STATUSES = ['draft', 'sent', 'accepted', 'declined'];

    protected $fillable = [
        'status', 'partner_id', 'customer', 'title', 'issue_date', 'valid_until', 'currency',
        'lines', 'discount_type', 'discount_value', 'net', 'vat', 'gross',
        'intro_text', 'outro_text', 'note',
    ];

    protected $casts = [
        'customer' => 'array',
        'lines' => 'array',
        'issue_date' => 'date',
        'valid_until' => 'date',
        'discount_value' => 'decimal:2',
        'net' => 'decimal:2',
        'vat' => 'decimal:2',
        'gross' => 'decimal:2',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'partner_id' => 'integer',
        'seq' => 'integer',
        'year' => 'integer',
        'converted_invoice_id' => 'integer',
        'converted_project_id' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsTo<FinancePartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'partner_id');
    }

    /**
     * Whether the price no longer stands.
     *
     * Derived rather than stored: a date does not need a nightly job to become
     * true, and a stored flag would be wrong between midnight and the job.
     */
    public function isExpired(): bool
    {
        if ($this->status !== 'sent' || $this->valid_until === null) {
            return false;
        }

        return $this->valid_until->endOfDay()->isPast();
    }

    /**
     * A quote that has left the house must not be edited.
     *
     * The customer holds a document with this number on it; changing what it
     * says while keeping the number would make the two disagree. A new version
     * is a new quote.
     */
    public function isLocked(): bool
    {
        return $this->status !== 'draft';
    }
}
