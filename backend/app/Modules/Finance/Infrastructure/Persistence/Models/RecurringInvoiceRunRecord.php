<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RecurringInvoiceRunRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_recurring_invoice_runs';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'template_id' => 'integer', 'template_version_id' => 'integer',
            'scheduled_for' => 'immutable_datetime', 'scheduled_local_date' => 'immutable_date',
            'invoice_id' => 'integer', 'delivery_id' => 'integer', 'attempts' => 'integer',
            'claimed_at' => 'immutable_datetime', 'claim_expires_at' => 'immutable_datetime',
            'next_retry_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        $this->assertInsert();

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Recurring invoice runs may change only through repository compare-and-set writes.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Recurring invoice runs may change only through repository compare-and-set writes.');
    }

    /** @return GuardedMutationBuilder<RecurringInvoiceRunRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Recurring invoice runs may change only through repository compare-and-set writes.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Recurring invoice runs may change only through repository compare-and-set writes.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Recurring invoice runs cannot be deleted directly.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Recurring invoice runs may change only through repository compare-and-set writes.');
        }
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<RecurringInvoiceTemplateRecord, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoiceTemplateRecord::class, 'template_id');
    }

    /** @return BelongsTo<RecurringInvoiceTemplateVersionRecord, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoiceTemplateVersionRecord::class, 'template_version_id');
    }

    /** @return BelongsTo<InvoiceRecord, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceRecord::class, 'invoice_id');
    }

    /** @return BelongsTo<InvoiceDeliveryRecord, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(InvoiceDeliveryRecord::class, 'delivery_id');
    }
}
