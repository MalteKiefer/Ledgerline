<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectDocumentLinkRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_project_document_links';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer',
            'document_series_id' => 'integer', 'pinned_revision_id' => 'integer',
            'metadata_snapshot' => 'array', 'attached_by' => 'integer',
            'attached_at' => 'immutable_datetime', 'detached_by' => 'integer',
            'detached_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ProjectRecord, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectRecord::class, 'project_id');
    }

    /** @return BelongsTo<DocumentSeriesRecord, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function pinnedRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'pinned_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function attacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }

    /** @return BelongsTo<User, $this> */
    public function detacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detached_by');
    }
}
