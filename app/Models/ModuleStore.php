<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One opaque sealed store per (user, module) — the per-module split of the old
 * per-module split of the old monolith workspace manifest. The browser seals each module's collection with
 * the vault key; the server stores only ciphertext + an optimistic-concurrency
 * version. Composite (user_id, module) primary key.
 *
 * @property int $version
 * @property string|null $ciphertext
 */
#[Fillable(['user_id', 'module', 'ciphertext', 'version'])]
class ModuleStore extends Model
{
    protected $table = 'module_stores';

    // Composite primary key (user_id + module); Eloquent needs incrementing off.
    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = true;
}
