<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
