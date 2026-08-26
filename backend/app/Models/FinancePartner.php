<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A business partner / Geschäftspartner (plaintext-relational pivot). Rows are
 * private per user via OwnsUserData. All columns — including the contact PII
 * (address/email/phone/vat_id) and the list of contact people — are plaintext
 * at rest (encryption removed in v1.516.0).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $category
 * @property string|null $kind
 * @property string|null $url
 * @property string|null $logo
 * @property string|null $note
 * @property string|null $address
 * @property string|null $delivery_address
 * @property int|null $payment_terms_days
 * @property string|null $discount_percent
 * @property Carbon|null $archived_at
 * @property string|null $customer_number
 * @property string|null $email
 * @property string|null $invoice_email
 * @property string|null $phone
 * @property string|null $vat_id
 * @property string|null $hourly_rate
 * @property string|null $currency
 * @property array<int, array<string, mixed>>|null $contacts
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinancePartner extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** What the partner is to us. `kind` carried no meaning before v1.782.0. */
    public const KINDS = ['customer', 'supplier', 'both', 'lead'];

    protected $fillable = [
        'name', 'category', 'kind', 'url', 'logo', 'note',
        'address', 'delivery_address', 'email', 'invoice_email', 'phone', 'vat_id', 'contacts',
        'hourly_rate', 'currency', 'payment_terms_days', 'discount_percent',
    ];

    protected $casts = [
        'contacts' => 'array',
        'version' => 'integer',
        'hourly_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'archived_at' => 'datetime',
    ];

    /** @return HasMany<FinancePartnerNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(FinancePartnerNote::class, 'finance_partner_id');
    }

    /**
     * Hidden from the pickers but not gone.
     *
     * An old customer's documents must keep pointing at their partner, so the
     * useful state is "do not offer this any more", not deletion.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
