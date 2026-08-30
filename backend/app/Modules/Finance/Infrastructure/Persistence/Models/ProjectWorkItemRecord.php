<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProjectWorkItemRecord extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $table = 'finance_project_work_items';

    protected $fillable = [
        'title', 'description', 'starts_on', 'due_on', 'estimate_quantity_scaled',
        'is_milestone', 'sort', 'product_reference',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer',
            'starts_on' => 'immutable_date', 'due_on' => 'immutable_date',
            'estimate_quantity_scaled' => 'integer', 'is_milestone' => 'boolean',
            'sort' => 'integer', 'source_revision_id' => 'integer',
            'source_line_index' => 'integer', 'version' => 'integer',
            'created_by' => 'integer', 'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<ProjectRecord, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectRecord::class, 'project_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'source_revision_id');
    }

    /** @return HasMany<ProjectTimeEntryRecord, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntryRecord::class, 'work_item_id');
    }
}
