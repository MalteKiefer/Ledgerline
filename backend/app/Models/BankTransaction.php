<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A bank-statement booking (plaintext-relational pivot). Rows are private per
 * user via OwnsUserData. date/amount + vat_cat drive stats and filtering. All
 * columns — including the free-text booking details (counterparty/purpose/
 * IBAN/…) and the attached receipts list — are plaintext at rest (encryption
 * removed in v1.516.0). Receipt file bytes live plaintext on the file disk;
 * each entry references its blob_path.
 *
 * @property int $id
 * @property int $user_id
 * @property int $payment_method_id
 * @property Carbon|null $date
 * @property string $amount
 * @property string|null $vat_cat
 * @property string|null $sig
 * @property int|null $invoice_id
 * @property string|null $invoice_number
 * @property int|null $finance_project_id
 * @property string|null $counterparty
 * @property string|null $counterparty_iban
 * @property string|null $bic
 * @property string|null $purpose
 * @property string|null $booking_text
 * @property string|null $eref
 * @property array<int, array<string, mixed>>|null $receipts
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class BankTransaction extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = [
        'payment_method_id', 'date', 'amount', 'vat_cat', 'sig',
        'invoice_id', 'invoice_number', 'finance_project_id',
        'counterparty', 'counterparty_iban', 'bic', 'purpose', 'booking_text', 'eref', 'receipts',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'receipts' => 'array',
        'version' => 'integer',
    ];
}
