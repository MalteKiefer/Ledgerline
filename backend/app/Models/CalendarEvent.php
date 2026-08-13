<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A calendar event. The raw VCALENDAR/VEVENT (iCalendar) is authoritative; other
 * columns are denormalised for list/range-query/search. Owner-scope comes via the
 * calendar relation (identical to Contact → AddressBook). The denormalised columns
 * are set by the writer/persister, never client-supplied.
 *
 * @property string $id
 * @property string $calendar_id
 * @property string $uri
 * @property string $etag
 * @property string|null $uid
 * @property string $component
 * @property string $ics
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $location
 * @property float|null $geo_lat
 * @property float|null $geo_lon
 * @property CarbonImmutable|null $dtstart
 * @property CarbonImmutable|null $dtend
 * @property bool $all_day
 * @property string|null $rrule
 * @property CarbonImmutable|null $recurrence_until
 * @property string|null $status
 * @property int|null $alarm_minutes_before
 * @property int $sequence
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'calendar_id', 'uri', 'etag', 'uid', 'component', 'ics',
    'summary', 'description', 'location', 'geo_lat', 'geo_lon', 'dtstart', 'dtend', 'all_day',
    'rrule', 'recurrence_until', 'status', 'alarm_minutes_before', 'sequence',
])]
class CalendarEvent extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'dtstart' => 'immutable_datetime',
            'dtend' => 'immutable_datetime',
            'recurrence_until' => 'immutable_datetime',
            'all_day' => 'boolean',
            'sequence' => 'integer',
            'alarm_minutes_before' => 'integer',
            'geo_lat' => 'float',
            'geo_lon' => 'float',
        ];
    }

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
