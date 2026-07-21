<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The user's sealed gallery index (photo list + album + people structure) as a
 * single opaque ciphertext + optimistic-concurrency version. Separate from the
 * per-module stores so gallery churn never re-seals notes/todos.
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class GalleryStore extends Model
{
    protected $table = 'gallery_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
