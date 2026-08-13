<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cross-user calendar share: the owner grants a registered user viewer or
 * editor access to one of their calendars. Owner-scoped for management (owner
 * column `owner_id`); the recipient side resolves by recipient_id with the
 * scope removed.
 *
 * @property int $id
 * @property int $owner_id
 * @property int $calendar_id
 * @property int $recipient_id
 * @property string $role
 */
class CalendarShare extends Model
{
    use OwnsUserData;

    protected $fillable = [];

    public function ownerColumn(): string
    {
        return 'owner_id';
    }

    public function canWrite(): bool
    {
        return $this->role === 'editor';
    }

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
