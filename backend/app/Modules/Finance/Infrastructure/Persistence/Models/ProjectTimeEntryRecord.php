<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProjectTimeEntryRecord extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $table = 'finance_project_time_entries';

    protected $fillable = [
        'worked_on', 'quantity_scaled', 'description', 'billable',
        'hourly_rate_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer', 'work_item_id' => 'integer',
            'worked_on' => 'immutable_date', 'quantity_scaled' => 'integer',
            'billable' => 'boolean', 'hourly_rate_minor' => 'integer',
            'invoiced_at' => 'immutable_datetime', 'version' => 'integer',
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

    /** @return BelongsTo<ProjectWorkItemRecord, $this> */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkItemRecord::class, 'work_item_id');
    }
}
