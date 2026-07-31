<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An invoice (plaintext-relational pivot). Rows are private per user via
 * OwnsUserData. number/seq/year + money columns (gross/net/vat) drive GoBD
 * numbering + VAT-return/revenue stats. The customer snapshot, line items, note
 * and GoBD correction history are JSON/array columns. All columns are plaintext
 * at rest (encryption removed in v1.516.0). number/seq/year/version/version_seq/
 * pdf_path are server-managed (assigned on finalisation / never mass-assigned).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $number
 * @property int|null $seq
 * @property int|null $year
 * @property string $status
 * @property string $type
 * @property int|null $cancels_invoice_id
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property string|null $skonto_percent
 * @property int|null $skonto_days
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property string $currency
 * @property string|null $vat_rate
 * @property string|null $gross
 * @property string|null $net
 * @property string|null $vat
 * @property bool $imported
 * @property Carbon|null $paid_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $reminded_at
 * @property int $reminder_count
 * @property string|null $payment_account
 * @property int|null $partner_id
 * @property string|null $pdf_path
 * @property array<string, mixed>|null $customer
 * @property array<int, array<string, mixed>>|null $lines
 * @property string|null $note
 * @property array<int, array<string, mixed>>|null $versions
 * @property int $version_seq
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = [
        'number', 'seq', 'year', 'status', 'issue_date', 'due_date', 'currency',
        'vat_rate', 'gross', 'net', 'vat', 'imported', 'paid_at', 'payment_account',
        'partner_id', 'customer', 'lines', 'note', 'versions',
        // Slice B: document kind + global discount + Skonto terms are user-editable.
        // cancels_invoice_id stays NON-fillable (server-set on the Storno action only).
        'type', 'discount_type', 'discount_value', 'skonto_percent', 'skonto_days',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        // Lifecycle columns — server-set via forceFill (NOT fillable).
        'sent_at' => 'datetime',
        'reminded_at' => 'datetime',
        'reminder_count' => 'integer',
        'vat_rate' => 'decimal:2',
        'gross' => 'decimal:2',
        'net' => 'decimal:2',
        'vat' => 'decimal:2',
        'imported' => 'boolean',
        'customer' => 'array',
        'lines' => 'array',
        'versions' => 'array',
        'seq' => 'integer',
        'year' => 'integer',
        'version' => 'integer',
        'version_seq' => 'integer',
        // Slice B: credit notes + discount + Skonto (money as decimal:2 for parity).
        'cancels_invoice_id' => 'integer',
        'discount_value' => 'decimal:2',
        'skonto_percent' => 'decimal:2',
        'skonto_days' => 'integer',
    ];

    /**
     * @return BelongsTo<FinancePartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'partner_id');
    }

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
