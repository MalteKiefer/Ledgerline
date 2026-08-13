<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A sieve-lite ingest rule: matched against each newly-parsed message at ingest
 * (MaildirIngestor). match_json holds equality/contains conditions
 * (from/to/subject/folder/has_attachment); action_json the effect
 * (add_label/mark_read/trash/skip). Owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $enabled
 * @property int $priority
 * @property array<string, mixed> $match_json
 * @property array<string, mixed> $action_json
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class MailRule extends Model
{
    use AssignsOwner;

    protected $fillable = ['name', 'enabled', 'priority', 'match_json', 'action_json'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'match_json' => 'array',
            'action_json' => 'array',
        ];
    }
}
