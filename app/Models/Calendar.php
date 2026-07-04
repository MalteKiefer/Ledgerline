<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarUri;
use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A CalDAV calendar collection (may be a read-only subscription to a remote feed). */
#[Fillable([
    'user_id', 'name', 'uri', 'color', 'description', 'components', 'synctoken',
    'subscription_url', 'read_only', 'refresh_minutes', 'refreshed_at',
])]
class Calendar extends Model
{
    use HasUuids;
    use OwnsUserData;

    /** Default calendar colour, and the validation rule for a user-supplied one. */
    public const DEFAULT_COLOR = '#3366cc';

    public const COLOR_RULE = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/'];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synctoken' => 'integer',
            'read_only' => 'boolean',
            'refresh_minutes' => 'integer',
            'refreshed_at' => 'datetime',
            // Feed URLs may embed credentials/secret tokens → encrypt at rest.
            'subscription_url' => 'encrypted',
        ];
    }

    public function isReadOnly(): bool
    {
        return (bool) $this->read_only;
    }

    /** The virtual calendar that exposes the shared to-dos as VTODO over CalDAV. */
    public function isTasks(): bool
    {
        return $this->uri === CalendarUri::Tasks->value;
    }

    /** A virtual calendar not backed by the calendar_objects table for writes. */
    public function isVirtual(): bool
    {
        return CalendarUri::isVirtual((string) $this->uri);
    }

    /** Whether a user may create/edit events here through the web UI or CalDAV. */
    public function isWritableByUser(): bool
    {
        return ! $this->isReadOnly() && ! $this->isVirtual();
    }

    /** Reserved calendars that must not be deleted through the UI. */
    public function isUndeletable(): bool
    {
        return CalendarUri::isUndeletable((string) $this->uri);
    }
}
