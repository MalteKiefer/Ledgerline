<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A gallery photo (plaintext-relational). Bytes live on the files disk under
 * gallery/{uuid}; this row holds the metadata. Owner-scoped via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $storage_path
 * @property string $name
 * @property string|null $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property bool $favorite
 * @property string|null $sha256
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class GalleryPhoto extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['name', 'favorite'];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'favorite' => 'boolean',
        'version' => 'integer',
    ];
}
