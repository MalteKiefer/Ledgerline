<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved recipient you can encrypt files to: someone else's PGP public key or
 * S/MIME certificate. Public material only (no secret), owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type pgp|smime
 * @property string $label
 * @property ?string $fingerprint
 * @property ?string $public_key
 * @property ?string $cert_pem
 */
class CryptoRecipient extends Model
{
    use AssignsOwner;
    use OwnsUserData;

    public const TYPES = ['pgp', 'smime'];

    protected $guarded = ['*'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
