<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SharesWithUsers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A file-browser folder (plain row, client-generated UUID id), private to its user. */
#[Fillable(['id', 'parent_id', 'name'])]
class FileFolder extends Model
{
    use SharesWithUsers;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';
}
