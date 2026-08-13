<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One emoji reaction per user per photo (toggle/replace). NOT owner-scoped —
 * a share recipient reacts to the owner's photo; controller gates access.
 *
 * @property int $id
 * @property int $gallery_photo_id
 * @property int $user_id
 * @property string $emoji
 */
class GalleryPhotoReaction extends Model
{
    public $timestamps = false;

    protected $fillable = ['gallery_photo_id', 'user_id', 'emoji'];

    protected $casts = ['created_at' => 'datetime'];
}
