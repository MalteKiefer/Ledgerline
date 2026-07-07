<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A bookmark folder, private to its owning user. */
#[Fillable(['name', 'parent_id'])]
class BookmarkFolder extends Model
{
    use OwnsUserData;
}
