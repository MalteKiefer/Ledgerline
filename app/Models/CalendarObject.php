<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A calendar object (VEVENT/VTODO). The raw ICS is authoritative. */
#[Fillable([
    'calendar_id', 'uri', 'etag', 'uid', 'ics', 'component',
    'summary', 'starts_at', 'ends_at', 'all_day', 'rrule', 'alarm_minutes',
])]
class CalendarObject extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'alarm_minutes' => 'integer',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
