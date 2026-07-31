<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user's health profile (plaintext-relational pivot). One row per user via
 * OwnsUserData. birthdate + weight_goal_kg are GDPR Art. 9 sensitive → stored
 * with a Laravel `encrypted` cast; height_cm/sex are non-sensitive plaintext.
 * Display units live on UserSetting, not here. Age/BMI are derived client-side.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $birthdate
 * @property int|null $height_cm
 * @property string|null $sex
 * @property string|null $weight_goal_kg
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HealthProfile extends Model
{
    use OwnsUserData;

    protected $fillable = ['birthdate', 'height_cm', 'sex', 'weight_goal_kg'];

    protected $casts = [
        'height_cm' => 'integer',
        'version' => 'integer',
    ];
}
