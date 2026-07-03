<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stored file's metadata (plain row, client-generated UUID id). The bytes
 * live unencrypted on the files disk at "files/{blob}". Trashing is Laravel
 * soft-deletion, but the client manifest owns the trashed timestamp, so the
 * sync sets deleted_at directly (see FileController::sync).
 */
#[Fillable(['id', 'file_folder_id', 'name', 'mime', 'size', 'blob', 'tags'])]
class StoredFile extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'tags' => 'array',
        ];
    }
}
