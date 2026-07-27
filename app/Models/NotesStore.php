<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user sealed root of the sharded notes store (store merge-safety spec §3b).
 * Holds only ciphertext (the shard pointer table + collections) + an optimistic
 * version — the record shards themselves live in the notes_blobs ledger. Mirrors
 * FilesStore / GalleryStore.
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class NotesStore extends Model
{
    protected $table = 'notes_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
