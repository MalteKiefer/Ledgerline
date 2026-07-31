<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The user's sealed files index (folder tree + file record pointers) as a single
 * opaque ciphertext + optimistic-concurrency version. Store v3 (§4.2): Files has
 * its own sharded store, separate from the per-module stores, so
 * files churn never re-seals the other modules. The heavy file records live in
 * content-addressed shard blobs (the files disk ledger).
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class FilesStore extends Model
{
    protected $table = 'files_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
