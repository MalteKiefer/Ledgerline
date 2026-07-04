<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Basic-auth login for external CardDAV clients (separate from the app session). */
#[Fillable(['user_id', 'username', 'password_hash', 'last_used_at'])]
class DavCredential extends Model
{
    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
