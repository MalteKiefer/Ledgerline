<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A CalDAV calendar collection (may be a read-only subscription to a remote feed). */
#[Fillable([
    'user_id', 'name', 'uri', 'color', 'description', 'components', 'synctoken',
    'subscription_url', 'read_only', 'refresh_minutes', 'refreshed_at',
])]
class Calendar extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synctoken' => 'integer',
            'read_only' => 'boolean',
            'refresh_minutes' => 'integer',
            'refreshed_at' => 'datetime',
        ];
    }

    public function objects(): HasMany
    {
        return $this->hasMany(CalendarObject::class);
    }

    public function isReadOnly(): bool
    {
        return (bool) $this->read_only;
    }
}
