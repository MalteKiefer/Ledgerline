<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A registered WebAuthn passkey / hardware key (owner-scoped). Public-key only —
 * `source` is the serialized PublicKeyCredentialSource; no secret at rest.
 *
 * @property int $id
 * @property int $user_id
 * @property string $credential_id
 * @property string|null $name
 * @property string $source
 * @property string|null $aaguid
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 */
class WebauthnCredential extends Model
{
    use OwnsUserData;

    protected $fillable = ['name'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}
