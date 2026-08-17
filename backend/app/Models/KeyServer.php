<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's configured HKP public-keyserver (see App\Support\Crypto\Keyserver
 * for the client). Non-secret operational config, owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $url
 * @property bool $enabled
 */
class KeyServer extends Model
{
    use AssignsOwner;
    use OwnsUserData;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CryptoRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CryptoRecipient::class);
    }
}
