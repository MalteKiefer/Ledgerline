<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A photo↔track coupling (plaintext-relational pivot). Rows are private per user
 * via OwnsUserData. photo_id is an opaque gallery photo id (gallery stays ZK — a
 * reference only). lat/lng is a low-precision map coordinate for fast rendering.
 *
 * @property int $id
 * @property int $user_id
 * @property int $explore_track_id
 * @property string $photo_id
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExploreCoupling extends Model
{
    use OwnsUserData;

    protected $fillable = ['explore_track_id', 'photo_id', 'lat', 'lng', 'source'];

    protected $casts = [
        'lat' => 'decimal:6',
        'lng' => 'decimal:6',
    ];
}
