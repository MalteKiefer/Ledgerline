<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A persisted mail filter set, surfaced as a virtual folder. Owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $filters_json
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class MailSavedSearch extends Model
{
    use AssignsOwner;

    protected $fillable = ['name', 'filters_json'];

    protected function casts(): array
    {
        return ['filters_json' => 'array'];
    }
}
