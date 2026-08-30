<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentAllocationBatchRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_payment_allocation_batches';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'payment_id' => 'integer', 'created_by' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        $this->assertInsert();

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payment allocation batches are append-only.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payment allocation batches are append-only.');
    }

    /** @return GuardedMutationBuilder<PaymentAllocationBatchRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Payment allocation batches are append-only.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Payment allocation batches are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Payment allocation batches are append-only.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Payment allocation batches are append-only.');
        }
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<PaymentRecord, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class, 'payment_id');
    }

    /** @return HasMany<PaymentAllocationRecord, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocationRecord::class, 'allocation_batch_id');
    }
}
