<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A user-level contact group, mirrored to each member card's CATEGORIES. */
#[Fillable(['user_id', 'name'])]
class ContactGroup extends Model
{
    use HasUuids;
    use OwnsUserData;

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group', 'group_id', 'contact_id');
    }
}
