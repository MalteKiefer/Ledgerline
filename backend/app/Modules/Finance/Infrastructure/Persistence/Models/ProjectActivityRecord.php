<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectActivityRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_project_activities';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer', 'payload' => 'array',
            'created_by' => 'integer', 'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
