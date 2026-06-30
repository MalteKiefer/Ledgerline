<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single labelled email address belonging to a contact person.
 *
 * The label is free text; the values below are only suggestions offered in the
 * UI. contact_id is not mass-assignable; it is set via the relationship.
 */
#[Fillable(['label', 'email'])]
class ContactEmail extends Model
{
    /** @use HasFactory<ContactEmailFactory> */
    use HasFactory;

    /**
     * Suggested labels offered as autocomplete hints (custom values allowed).
     *
     * @var list<string>
     */
    public const SUGGESTED_LABELS = ['Work', 'Personal', 'Billing', 'Support', 'Other'];

    /**
     * The contact this email address belongs to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
