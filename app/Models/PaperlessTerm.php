<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A cached Paperless term — a tag, document type or correspondent — mirrored
 * locally so the transfer modal can offer them without a live API round-trip.
 */
#[Fillable(['kind', 'paperless_id', 'name', 'color'])]
class PaperlessTerm extends Model
{
    public const KINDS = ['tag', 'document_type', 'correspondent'];

    protected function casts(): array
    {
        return [
            'paperless_id' => 'integer',
        ];
    }
}
