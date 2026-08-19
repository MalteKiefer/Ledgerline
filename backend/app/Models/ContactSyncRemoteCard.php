<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Durable remote identity and last-confirmed local version for one replica. */
class ContactSyncRemoteCard extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['remote_deleted_at' => 'datetime']; }

    /** @return BelongsTo<ContactSyncSource, $this> */
    public function source(): BelongsTo { return $this->belongsTo(ContactSyncSource::class, 'source_id'); }
}
