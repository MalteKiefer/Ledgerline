<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProjectLedgerEntryRecord extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $table = 'finance_project_ledger_entries';

    protected $fillable = [
        'direction', 'amount_minor', 'currency', 'occurred_on', 'title', 'note',
        'category_reference', 'payment_method_reference',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer', 'amount_minor' => 'integer',
            'occurred_on' => 'immutable_date', 'legacy_metadata' => 'array',
            'version' => 'integer', 'created_by' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
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
}
