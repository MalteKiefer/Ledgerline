<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch office (Niederlassung) belonging to a customer.
 *
 * The country is stored as an ISO 3166-1 alpha-2 code. A branch may optionally
 * name one of the customer's contacts as its manager (Niederlassungsleiter).
 * customer_id is not mass-assignable; it is set from the route-bound customer.
 */
#[Fillable([
    'name',
    'street',
    'postal_code',
    'city',
    'country',
    'phone',
    'email',
    'manager_contact_id',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * The customer this branch belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The contact who manages this branch, if any.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'manager_contact_id');
    }
}
