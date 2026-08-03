<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user sealed root of the sharded contacts store (merge-safety spec §3b). Mirrors
 * NotesStore/InvoicesStore. The contact records live as content-addressed shard blobs
 * in the existing contact_blobs ledger (shared with avatar blobs, content-addressed so
 * refs never collide).
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class ContactsStore extends Model
{
    protected $table = 'contacts_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
