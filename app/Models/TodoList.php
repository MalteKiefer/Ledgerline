<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A named to-do list, private to its owning user. The name is sealed (ZK). */
#[Fillable(['name', 'is_encrypted'])]
class TodoList extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }
}
