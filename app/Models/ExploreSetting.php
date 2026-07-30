<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user's Explore preferences (plaintext-relational pivot). One row per user via
 * OwnsUserData: the photo↔track matching tolerances. Non-secret → plaintext.
 *
 * @property int $id
 * @property int $user_id
 * @property int $coupling_time_tolerance_s
 * @property int $coupling_distance_tolerance_m
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExploreSetting extends Model
{
    use OwnsUserData;

    protected $fillable = ['coupling_time_tolerance_s', 'coupling_distance_tolerance_m'];

    protected $casts = [
        'coupling_time_tolerance_s' => 'integer',
        'coupling_distance_tolerance_m' => 'integer',
    ];
}
