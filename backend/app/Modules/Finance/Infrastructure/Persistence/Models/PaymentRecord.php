<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_payments';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'amount_minor' => 'integer', 'received_at' => 'immutable_datetime',
            'payment_method_id' => 'integer', 'version' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        $this->assertInsert();

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payments are repository-owned append-only records.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Payments are repository-owned append-only records.');
    }

    /** @return GuardedMutationBuilder<PaymentRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Payments are repository-owned append-only records.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Payments are repository-owned append-only records.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Payments are repository-owned append-only records.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Payments are repository-owned append-only records.');
        }
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<PaymentAllocationBatchRecord, $this> */
    public function allocationBatches(): HasMany
    {
        return $this->hasMany(PaymentAllocationBatchRecord::class, 'payment_id');
    }

    /** @return HasMany<PaymentAllocationRecord, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocationRecord::class, 'payment_id');
    }
}
