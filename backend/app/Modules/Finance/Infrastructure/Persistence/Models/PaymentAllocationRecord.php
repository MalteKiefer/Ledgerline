<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAllocationRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_payment_allocations';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'allocation_batch_id' => 'integer', 'payment_id' => 'integer',
            'invoice_id' => 'integer', 'amount_minor' => 'integer',
            'reverses_allocation_id' => 'integer', 'created_at' => 'immutable_datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        $this->assertInsert();

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payment allocation entries are append-only.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payment allocation entries are append-only.');
    }

    /** @return GuardedMutationBuilder<PaymentAllocationRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Payment allocation entries are append-only.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Payment allocation entries are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Payment allocation entries are append-only.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Payment allocation entries are append-only.');
        }
    }

    /** @return BelongsTo<PaymentAllocationBatchRecord, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocationBatchRecord::class, 'allocation_batch_id');
    }

    /** @return BelongsTo<PaymentRecord, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class, 'payment_id');
    }

    /** @return BelongsTo<InvoiceRecord, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceRecord::class, 'invoice_id');
    }

    /** @return BelongsTo<PaymentAllocationRecord, $this> */
    public function reversedAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_allocation_id');
    }
}
