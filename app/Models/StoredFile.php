<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored file's metadata (plain row, client-generated UUID id). The bytes
 * live unencrypted on the files disk at "files/{blob}".
 */
#[Fillable(['id', 'file_folder_id', 'name', 'mime', 'size', 'blob', 'tags', 'trashed_at'])]
class StoredFile extends Model
{
    protected $table = 'files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'tags' => 'array',
            'trashed_at' => 'datetime',
        ];
    }
}
