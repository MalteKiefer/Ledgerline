<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A deadline read out of a document (contract end, notice period, warranty,
 * expiry). Owner-scoped like every other user row.
 *
 * A row is a FINDING until `confirmed_at` is set. Only a confirmed deadline is
 * reminded about — a pattern matcher will misread something eventually, and a
 * wrong date asserted as true is worse than no date at all.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source_type file|photo|mail|receipt
 * @property int $source_id
 * @property Carbon|null $due_on
 * @property string $kind
 * @property string|null $evidence The sentence it was read from.
 * @property string|null $label
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $reminded_at
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DocumentDeadline extends Model
{
    use OwnsUserData;

    protected $fillable = ['label', 'kind', 'due_on'];

    protected $casts = [
        'due_on' => 'date',
        'confirmed_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'reminded_at' => 'datetime',
        'version' => 'integer',
        'source_id' => 'integer',
    ];
}
