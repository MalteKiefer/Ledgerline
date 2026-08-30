<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InvoiceRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_invoices';

    protected $fillable = ['issue_date', 'due_date', 'partner_id', 'project_id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'document_series_id' => 'integer',
            'current_revision_id' => 'integer', 'year' => 'integer', 'sequence' => 'integer',
            'issue_date' => 'immutable_date', 'due_date' => 'immutable_date',
            'partner_id' => 'integer', 'project_id' => 'integer', 'source_revision_id' => 'integer',
            'finalized_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime',
            'allocated_minor' => 'integer', 'open_minor' => 'integer', 'version' => 'integer',
            'cancels_invoice_id' => 'integer', 'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<DocumentSeriesRecord, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'current_revision_id');
    }

    /** @return HasMany<InvoiceDeliveryRecord, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(InvoiceDeliveryRecord::class, 'invoice_id');
    }

    /** @return HasMany<PaymentAllocationRecord, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocationRecord::class, 'invoice_id');
    }
}
