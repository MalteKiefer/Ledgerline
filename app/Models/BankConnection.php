<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's link to one bank account via GoCardless Bank Account Data (PSD2/XS2A).
 * Holds only non-secret consent metadata (requisition/account ids, status,
 * expiry) — the workspace API credentials live encrypted on AppSettings, and the
 * short-lived GoCardless access token is fetched per sync, never persisted.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $payment_method_id
 * @property string $provider
 * @property string $institution_id
 * @property string|null $institution_name
 * @property string|null $requisition_id
 * @property string $reference
 * @property string|null $account_id
 * @property string $status
 * @property Carbon|null $consent_expires_at
 * @property Carbon|null $last_synced_at
 */
class BankConnection extends Model
{
    use OwnsUserData;

    protected $fillable = [
        'payment_method_id', 'provider', 'institution_id', 'institution_name',
        'requisition_id', 'reference', 'account_id', 'status',
        'consent_expires_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
