<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A named to-do list, private to its owning user. */
#[Fillable(['name'])]
class TodoList extends Model
{
    use OwnsUserData;
}
