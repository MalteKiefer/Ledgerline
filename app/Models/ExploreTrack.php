<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A GPS track (plaintext-relational pivot). Rows are private per user via
 * OwnsUserData. The ordered point list (`points`) and `note` are plaintext at
 * rest (encryption removed in v1.516.0); aggregate `stats` are used for
 * listing/sorting. blob_path (optional raw track file on disk) is server-set,
 * never mass-assigned. Track parsing (GPX/KML/TCX/FIT) happens client-side; the
 * server only stores the already-parsed points + stats.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $source_format
 * @property array<int, array<string, mixed>> $points
 * @property array<string, mixed>|null $stats
 * @property string|null $note
 * @property string|null $blob_path
 * @property Carbon|null $imported_at
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ExploreTrack extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'source_format', 'points', 'stats', 'note', 'imported_at'];

    protected $casts = [
        'points' => 'array',
        'stats' => 'array',
        'imported_at' => 'datetime',
        'version' => 'integer',
    ];

    /**
     * @return HasMany<ExploreCoupling, $this>
     */
    public function couplings(): HasMany
    {
        return $this->hasMany(ExploreCoupling::class);
    }
}
