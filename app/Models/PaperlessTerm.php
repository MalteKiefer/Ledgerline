<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A cached Paperless term — a tag, document type or correspondent — mirrored
 * locally so the transfer modal can offer them without a live API round-trip.
 * Per-user: each user syncs terms from their own Paperless instance.
 *
 * @property int $user_id
 * @property string $kind
 * @property int $paperless_id
 * @property string $name
 * @property string|null $color
 */
#[Fillable(['user_id', 'kind', 'paperless_id', 'name', 'color'])]
class PaperlessTerm extends Model
{
    use OwnsUserData;

    public const KINDS = ['tag', 'document_type', 'correspondent'];

    protected function casts(): array
    {
        return [
            'paperless_id' => 'integer',
        ];
    }
}
