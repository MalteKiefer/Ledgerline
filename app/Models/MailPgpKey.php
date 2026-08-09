<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's own PGP or S/MIME decryption key, used only server-side to decrypt
 * encrypted archived mail. The private key + passphrase are the sole secrets
 * here: `encrypted` cast (APP_KEY) + `$hidden` so they are never serialised or
 * returned by any API. Public material (public_key / cert_pem / fingerprint /
 * identities) is non-secret. Server-set via forceFill; `user_id` from context.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $label
 * @property ?string $key_fingerprint
 * @property ?string $key_id
 * @property ?string $public_key
 * @property string $private_key
 * @property ?string $passphrase
 * @property ?string $cert_pem
 * @property ?array<int, array{name?:?string, email:string}> $identities_json
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Hidden(['private_key', 'passphrase'])]
class MailPgpKey extends Model
{
    use AssignsOwner;

    public const TYPES = ['pgp', 'smime'];

    /** Server-set via forceFill only. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'passphrase' => 'encrypted',
            'identities_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
