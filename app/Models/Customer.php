<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    'website',
    'vat_id',
    'street',
    'postal_code',
    'city',
    'country',
    'notes',
    'default_rate_cents',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTeam, HasFactory;

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

    /**
     * The branch offices (Niederlassungen) belonging to this customer.
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * The files attached to this customer.
     *
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}
