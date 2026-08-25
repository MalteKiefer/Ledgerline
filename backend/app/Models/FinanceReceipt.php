<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A standalone finance receipt ("Fremdbeleg") — a receipt document that does not
 * need a bank transaction. Private per user via OwnsUserData; plaintext at rest.
 * The file bytes live plaintext on the files disk; blob_path is server-owned
 * (never mass-assigned). An optional bank_transaction_id links it to a booking —
 * or, when a vendor split one invoice across several separate charges,
 * linked_transaction_ids holds ALL of them instead (mutually exclusive with
 * bank_transaction_id, never both set at once).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $bank_transaction_id
 * @property array<int, int>|null $linked_transaction_ids
 * @property int|null $finance_project_id
 * @property string $blob_path
 * @property string $name
 * @property string|null $mime
 * @property int $size
 * @property string|null $sig
 * @property string $kind
 * @property string|null $category
 * @property array<int, string>|null $tags
 * @property string|null $vat
 * @property numeric-string|null $amount
 * @property string|null $currency
 * @property Carbon|null $date
 * @property string|null $order_ref
 * @property string|null $doc_number
 * @property string|null $note
 * @property int|null $partner_id
 * @property string|null $ocr
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceReceipt extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    // blob_path / size / sig are server-set (never trust the client), so they are
    // NOT fillable — the controller sets them via forceFill on create.
    protected $fillable = [
        'bank_transaction_id', 'linked_transaction_ids', 'finance_project_id', 'name', 'kind', 'scope',
        'category', 'tags', 'vat', 'amount', 'currency', 'date', 'order_ref', 'doc_number',
        'note', 'partner_id', 'ocr',
    ];

    protected $casts = [
        'tags' => 'array',
        'linked_transaction_ids' => 'array',
        'size' => 'integer',
        'version' => 'integer',
        'bank_transaction_id' => 'integer',
        'finance_project_id' => 'integer',
        'partner_id' => 'integer',
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    /**
     * The booking this receipt documents, if it has been matched to one — it
     * supplies the scope when the receipt does not state one.
     *
     * @return BelongsTo<BankTransaction, $this>
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }
}
