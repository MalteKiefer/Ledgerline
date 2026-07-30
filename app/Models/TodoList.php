<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A todo list (plaintext-relational pivot, Phase 1). Owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 */
class TodoList extends Model
{
    use OwnsUserData;

    protected $fillable = ['name'];

    /** @return HasMany<Todo, $this> */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }
}
