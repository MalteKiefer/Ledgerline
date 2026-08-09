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
 * @property string $kind
 * @property string|null $country
 * @property string|null $subdivision
 * @property string|null $description
 * @property string $timezone
 * @property int $synctoken
 */
#[Fillable(['user_id', 'name', 'uri', 'color', 'kind', 'country', 'subdivision', 'description', 'timezone', 'synctoken'])]
class Calendar extends Model
{
    use HasUuids;
    use OwnsUserData;

    /** Ordinary editable/imported calendar. */
    public const KIND_NORMAL = 'normal';

    /** Generated, read-only: public holidays for a country (+ optional region). */
    public const KIND_HOLIDAYS = 'holidays';

    /** Generated, read-only: school holidays (Ferien) by country + subdivision. */
    public const KIND_SCHOOL_HOLIDAYS = 'school_holidays';

    /** Generated, read-only: contact birthdays (yearly-recurring). */
    public const KIND_BIRTHDAYS = 'birthdays';

    /** The special (server-generated, read-only) kinds. */
    public const SPECIAL_KINDS = [self::KIND_HOLIDAYS, self::KIND_SCHOOL_HOLIDAYS, self::KIND_BIRTHDAYS];

    protected function casts(): array
    {
        return ['synctoken' => 'integer'];
    }

    /** A special calendar is generated + read-only (no manual event add/edit). */
    public function isSpecial(): bool
    {
        return in_array($this->kind, self::SPECIAL_KINDS, true);
    }

    /** @return HasMany<CalendarEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
