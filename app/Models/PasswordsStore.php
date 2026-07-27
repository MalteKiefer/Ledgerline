<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user sealed root of the sharded passwords store (store merge-safety spec §3b).
 * Ciphertext (shard pointer table + secretFolders collection) + optimistic version;
 * the record shards live in the passwords_blobs ledger. Mirrors NotesStore/FilesStore.
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class PasswordsStore extends Model
{
    protected $table = 'passwords_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
