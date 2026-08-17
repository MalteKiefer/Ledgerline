<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved recipient you can encrypt files to: someone else's PGP public key or
 * S/MIME certificate. Public material only (no secret), owner-scoped. A key
 * imported via a keyserver search (see KeyServerController) records which
 * server + id it came from, so it can later be refreshed from the same
 * source; a manually pasted key has key_server_id/key_id null.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $key_server_id
 * @property string $type pgp|smime
 * @property string $label
 * @property ?string $fingerprint
 * @property ?string $key_id
 * @property ?string $public_key
 * @property ?string $cert_pem
 * @property ?Carbon $refreshed_at
 */
class CryptoRecipient extends Model
{
    use AssignsOwner;
    use OwnsUserData;

    public const TYPES = ['pgp', 'smime'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['refreshed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<KeyServer, $this> */
    public function keyServer(): BelongsTo
    {
        return $this->belongsTo(KeyServer::class);
    }
}
