<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer company record.
 *
 * Only mass-assignable, user-editable fields are listed in the Fillable
 * attribute; identifiers and timestamps are managed by the framework. Contact
 * persons and projects relate to a customer through foreign keys added in
 * later milestones.
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'vat_id',
    'street',
    'postal_code',
    'city',
    'country',
    'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * The contact persons belonging to this customer.
     *
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * The projects belonging to this customer.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
