<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A writable external CardDAV replica; Ledgerline remains canonical.
 *
 * @property string $id
 * @property int $user_id
 * @property string $address_book_id
 * @property string $name
 * @property string $provider
 * @property string $endpoint
 * @property string $auth_type
 * @property string|null $username
 * @property string|null $password
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property string|null $oauth_client_id
 * @property string|null $oauth_client_secret
 * @property string|null $oauth_state_hash
 * @property Carbon|null $access_token_expires_at
 * @property bool $enabled
 * @property bool $propagate_deletes
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $last_synced_at
 */
#[Fillable([
    'user_id', 'address_book_id', 'name', 'provider', 'endpoint', 'auth_type', 'username',
    'password', 'access_token', 'refresh_token', 'oauth_client_id', 'oauth_client_secret',
    'oauth_state_hash', 'access_token_expires_at', 'sync_token', 'enabled', 'propagate_deletes',
    'status', 'last_error', 'last_synced_at',
])]
#[Hidden(['password', 'access_token', 'refresh_token', 'oauth_client_id', 'oauth_client_secret', 'oauth_state_hash'])]
class ContactSyncSource extends Model
{
    use HasUuids;
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'password' => 'encrypted', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted',
            'oauth_client_id' => 'encrypted', 'oauth_client_secret' => 'encrypted',
            'access_token_expires_at' => 'datetime', 'last_synced_at' => 'datetime',
            'enabled' => 'boolean', 'propagate_deletes' => 'boolean',
        ];
    }

    /** @return BelongsTo<AddressBook, $this> */
    public function addressBook(): BelongsTo
    {
        return $this->belongsTo(AddressBook::class);
    }

    /** @return HasMany<ContactSyncRemoteCard, $this> */
    public function remoteCards(): HasMany
    {
        return $this->hasMany(ContactSyncRemoteCard::class, 'source_id');
    }
}
