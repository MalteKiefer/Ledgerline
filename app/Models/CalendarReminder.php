<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One queued calendar reminder. Metadata only: an absolute UTC fire time plus the
 * opaque client event/occurrence ids — never the event title or content. `user_id`
 * is stamped server-side (AssignsOwner). The scheduler pushes a generic
 * notification when remind_at passes; the server never learns what it is for.
 *
 * @property int $id
 * @property int $user_id
 * @property string $event_id
 * @property ?string $recurrence_id
 * @property Carbon $remind_at
 * @property ?Carbon $delivered_at
 */
#[Fillable(['event_id', 'recurrence_id', 'remind_at'])]
class CalendarReminder extends Model
{
    use AssignsOwner;

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
