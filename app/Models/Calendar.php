<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CalDAV calendar collection.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $uri
 * @property string|null $color
 * @property string|null $description
 * @property string $timezone
 * @property int $synctoken
 */
#[Fillable(['user_id', 'name', 'uri', 'color', 'description', 'timezone', 'synctoken'])]
class Calendar extends Model
{
    use HasUuids;
    use OwnsUserData;

    protected function casts(): array
    {
        return ['synctoken' => 'integer'];
    }

    /** @return HasMany<CalendarEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
