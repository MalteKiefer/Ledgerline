<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user sealed root of the sharded invoices store (merge-safety spec §3b). Mirrors
 * NotesStore. The invoice records live in the invoices_blobs ledger.
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class InvoicesStore extends Model
{
    protected $table = 'invoices_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
