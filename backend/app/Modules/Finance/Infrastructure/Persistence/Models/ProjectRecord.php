<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProjectRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_project_records';

    protected $fillable = [
        'name', 'kind', 'partner_reference', 'starts_on', 'due_on',
        'budget_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'parent_project_id' => 'integer',
            'source_id' => 'integer',
            'starts_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'budget_minor' => 'integer',
            'version' => 'integer',
            'archived_at' => 'immutable_datetime',
            'created_by' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_project_id');
    }

    /** @return HasMany<ProjectRecord, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_project_id');
    }

    /** @return HasMany<ProjectWorkItemRecord, $this> */
    public function workItems(): HasMany
    {
        return $this->hasMany(ProjectWorkItemRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectTimeEntryRecord, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntryRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectLedgerEntryRecord, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ProjectLedgerEntryRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectDocumentLinkRecord, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(ProjectDocumentLinkRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectNoteRecord, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNoteRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectActivityRecord, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivityRecord::class, 'project_id');
    }

    /** @return HasMany<ProjectOperationRecord, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(ProjectOperationRecord::class, 'project_id');
    }
}
