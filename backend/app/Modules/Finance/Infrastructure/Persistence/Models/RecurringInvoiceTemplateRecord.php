<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RecurringInvoiceTemplateRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_recurring_invoice_templates';

    protected $fillable = [
        'mode', 'interval', 'timezone', 'start_date', 'end_date', 'run_time',
        'anchor_day', 'month_end_anchor', 'next_run_at', 'status', 'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'start_date' => 'immutable_date', 'end_date' => 'immutable_date',
            'anchor_day' => 'integer', 'month_end_anchor' => 'boolean',
            'next_run_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime',
            'current_version_id' => 'integer', 'version' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<RecurringInvoiceTemplateVersionRecord, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoiceTemplateVersionRecord::class, 'current_version_id');
    }

    /** @return HasMany<RecurringInvoiceTemplateVersionRecord, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RecurringInvoiceTemplateVersionRecord::class, 'template_id');
    }

    /** @return HasMany<RecurringInvoiceRunRecord, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(RecurringInvoiceRunRecord::class, 'template_id');
    }
}
