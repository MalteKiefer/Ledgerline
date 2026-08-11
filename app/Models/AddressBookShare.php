<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A viewer-only cross-user address-book share. Owner-private via OwnsUserData
 * (owner column `owner_id`); the recipient side resolves by recipient_id with
 * the scope removed and never mutates the owner's contacts.
 *
 * @property int $id
 * @property int $owner_id
 * @property int $recipient_id
 * @property string $address_book_id
 * @property Carbon|null $created_at
 */
class AddressBookShare extends Model
{
    use OwnsUserData;

    protected $fillable = [];

    public function ownerColumn(): string
    {
        return 'owner_id';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<AddressBook, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(AddressBook::class, 'address_book_id');
    }
}
