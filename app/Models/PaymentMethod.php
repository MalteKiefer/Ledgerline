<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A payment method / account (plaintext-relational pivot). Rows are private per
 * user via OwnsUserData. type/name/business + url/icon are plaintext; the
 * sensitive account identifiers (IBAN/BIC/card number/…) carry an `encrypted`
 * cast.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $name
 * @property bool $business
 * @property string|null $url
 * @property string|null $icon
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $bank
 * @property string|null $account_no
 * @property string|null $card_number
 * @property string|null $card_network
 * @property string|null $card_expiry
 * @property string|null $paypal_email
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PaymentMethod extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = [
        'type', 'name', 'business', 'url', 'icon',
        'iban', 'bic', 'bank', 'account_no',
        'card_number', 'card_network', 'card_expiry', 'paypal_email',
    ];

    protected $casts = [
        'business' => 'boolean',
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'bank' => 'encrypted',
        'account_no' => 'encrypted',
        'card_number' => 'encrypted',
        'card_network' => 'encrypted',
        'card_expiry' => 'encrypted',
        'paypal_email' => 'encrypted',
        'version' => 'integer',
    ];

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
