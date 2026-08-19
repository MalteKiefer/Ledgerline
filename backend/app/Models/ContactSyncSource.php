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

/** A writable external CardDAV replica; Ledgerline remains canonical. */
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
    public function addressBook(): BelongsTo { return $this->belongsTo(AddressBook::class); }

    /** @return HasMany<ContactSyncRemoteCard, $this> */
    public function remoteCards(): HasMany { return $this->hasMany(ContactSyncRemoteCard::class, 'source_id'); }
}
