<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactPhoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single labelled phone number belonging to a contact person.
 *
 * The label is free text; the values below are only suggestions offered in the
 * UI. contact_id is not mass-assignable; it is set via the relationship.
 */
#[Fillable(['label', 'phone'])]
class ContactPhone extends Model
{
    /** @use HasFactory<ContactPhoneFactory> */
    use HasFactory;

    /**
     * Suggested labels offered as autocomplete hints (custom values allowed).
     *
     * @var list<string>
     */
    public const SUGGESTED_LABELS = ['Work', 'Mobile', 'Home', 'Fax', 'Other'];

    /**
     * The contact this phone number belongs to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
