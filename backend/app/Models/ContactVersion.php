<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;

/** Append-only vCard recovery point, including remote deletions and conflicts. */
class ContactVersion extends Model
{
    use OwnsUserData;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
