<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A comment on a gallery photo. NOT owner-scoped: a share recipient may author
 * a comment on the owner's photo. Access is gated by the controller via the
 * photo-access check. `user_id` is the author.
 *
 * @property int $id
 * @property int $gallery_photo_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 */
class GalleryPhotoComment extends Model
{
    public $timestamps = false;

    protected $fillable = ['gallery_photo_id', 'user_id', 'body'];

    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
