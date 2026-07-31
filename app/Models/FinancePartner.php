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
 * private per user via OwnsUserData. name/category/kind + url/logo/note are
 * plaintext for listing; contact PII (address/email/phone/vat_id) plus the list
 * of contact people carry an `encrypted` cast.
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
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $vat_id
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

    protected $fillable = [
        'name', 'category', 'kind', 'url', 'logo', 'note',
        'address', 'email', 'phone', 'vat_id', 'contacts',
    ];

    protected $casts = [
        'contacts' => 'array',
        'version' => 'integer',
    ];

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'partner_id');
    }
}
