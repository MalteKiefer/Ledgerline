<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single health measurement (plaintext-relational pivot). Rows are private per
 * user via OwnsUserData. metric + ts stay plaintext so the server can
 * sort/filter/group for charts; the readings (v/v2) and note are Art. 9
 * sensitive → `encrypted` cast. Values stored in canonical units as strings.
 *
 * @property int $id
 * @property int $user_id
 * @property string $metric
 * @property Carbon $ts
 * @property string $v
 * @property string|null $v2
 * @property string|null $note
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HealthEntry extends Model
{
    use OwnsUserData;

    protected $fillable = ['metric', 'ts', 'v', 'v2', 'note'];

    protected $casts = [
        'ts' => 'datetime',
        'version' => 'integer',
    ];
}
