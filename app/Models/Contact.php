<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactFunction;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact person belonging to a customer.
 *
 * The "function" column is cast to the ContactFunction enum, so the model
 * always exposes a strongly-typed role and only ever persists the enum's
 * backing value. The owning customer_id is intentionally not mass-assignable;
 * it is set explicitly from the route-bound customer.
 */
#[Fillable(['name', 'email', 'phone', 'function'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'function' => ContactFunction::class,
        ];
    }

    /**
     * The customer this contact person belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
