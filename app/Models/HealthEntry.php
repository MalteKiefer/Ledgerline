<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single health measurement (plaintext-relational pivot). Rows are private per
 * user via OwnsUserData. metric + ts let the server sort/filter/group for
 * charts. All columns — including the Art. 9 readings (v/v2) and note — are
 * plaintext at rest (encryption removed in v1.516.0; confidentiality is an
 * infra concern). Values stored in canonical units as strings.
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
