<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user-managed receipt category (plaintext-relational pivot). A small managed
 * lookup list, unique per user; no soft deletes.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FinanceCategory extends Model
{
    use OwnsUserData;

    protected $fillable = ['name'];
}
