<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A retained previous sealed-root snapshot of a store (see the create_store_history
 * migration). Append-only from the app's side; pruned to the last N versions per
 * (user, module) on each save. The ciphertext is opaque — the server never reads it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $module
 * @property int $version
 * @property string $ciphertext
 * @property Carbon|null $created_at
 */
class StoreHistory extends Model
{
    public $timestamps = false;

    protected $table = 'store_history';

    protected $fillable = ['user_id', 'module', 'version', 'ciphertext', 'created_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
