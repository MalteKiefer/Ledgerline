<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A reusable HTML mail signature, private to its owning user. */
#[Fillable(['name', 'html', 'is_default'])]
class MailSignature extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
