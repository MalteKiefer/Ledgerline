<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CalDAV task (VTODO). The raw VCALENDAR/VTODO (iCalendar) is authoritative; the
 * other columns are denormalised for list/filter/search. Unlike CalendarEvent
 * (owner-scoped via its calendar), a todo carries its own `user_id` so it is
 * directly owner-scoped through OwnsUserData — the denormalised columns are set by
 * the writer/persister, never client-supplied.
 *
 * @property string $id
 * @property string $calendar_id
 * @property int $user_id
 * @property string $uri
 * @property string $etag
 * @property string|null $uid
 * @property string $ics
 * @property string|null $summary
 * @property string|null $description
 * @property string $status
 * @property int|null $priority
 * @property int|null $percent_complete
 * @property CarbonImmutable|null $due
 * @property CarbonImmutable|null $dtstart
 * @property CarbonImmutable|null $completed_at
 * @property bool $all_day
 * @property string|null $rrule
 * @property list<string>|null $categories
 * @property string|null $related_to
 * @property int $sequence
 * @property int $sort_order
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'calendar_id', 'user_id', 'uri', 'etag', 'uid', 'ics',
    'summary', 'description', 'status', 'priority', 'percent_complete',
    'due', 'dtstart', 'completed_at', 'all_day', 'rrule', 'categories',
    'related_to', 'sequence', 'sort_order',
])]
class CalendarTodo extends Model
{
    use HasUuids;
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'due' => 'immutable_datetime',
            'dtstart' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'priority' => 'integer',
            'percent_complete' => 'integer',
            'sequence' => 'integer',
            'sort_order' => 'integer',
            'categories' => 'array',
        ];
    }

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
