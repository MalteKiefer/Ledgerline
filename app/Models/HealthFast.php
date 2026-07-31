<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An intermittent-fasting window (plaintext-relational pivot). Rows are private
 * per user via OwnsUserData; end_at null = active. The single-active-fast
 * invariant is enforced by a partial unique DB index (see migration), not by
 * the client. start_at/end_at/target_hours are plaintext; note is `encrypted`.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property int $target_hours
 * @property string|null $note
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HealthFast extends Model
{
    use OwnsUserData;

    protected $fillable = ['start_at', 'end_at', 'target_hours', 'note'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'target_hours' => 'integer',
        'version' => 'integer',
    ];
}
