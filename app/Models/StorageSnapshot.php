<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Ops\StorageHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One day's storage usage per module. Written daily by ops:snapshot-storage
 * (and on demand by {@see StorageHistory}); read for the System page trend.
 */
#[Fillable([
    'captured_on', 'files_bytes', 'gallery_bytes', 'database_bytes', 'total_bytes',
])]
class StorageSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'files_bytes' => 'integer',
            'gallery_bytes' => 'integer',
            'database_bytes' => 'integer',
            'total_bytes' => 'integer',
        ];
    }
}
