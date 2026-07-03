<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A file-browser folder (plain row, client-generated UUID id). */
#[Fillable(['id', 'parent_id', 'name'])]
class FileFolder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';
}
