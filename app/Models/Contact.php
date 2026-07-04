<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ContactObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A contact card. The raw vCard 4.0 is authoritative; other columns are denormalised. */
#[ObservedBy(ContactObserver::class)]
#[Fillable([
    'address_book_id', 'uri', 'etag', 'vcard',
    'fn', 'first_name', 'last_name', 'org', 'emails', 'phones', 'has_photo',
])]
class Contact extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'emails' => 'array',
            'phones' => 'array',
            'has_photo' => 'boolean',
        ];
    }

    public function addressBook(): BelongsTo
    {
        return $this->belongsTo(AddressBook::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group', 'contact_id', 'group_id');
    }
}
