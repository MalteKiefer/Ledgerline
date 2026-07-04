<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SharesWithUsers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A CardDAV address book collection. */
#[Fillable(['user_id', 'name', 'uri', 'description', 'synctoken'])]
class AddressBook extends Model
{
    use HasUuids;
    use SharesWithUsers;

    protected function casts(): array
    {
        return ['synctoken' => 'integer'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
