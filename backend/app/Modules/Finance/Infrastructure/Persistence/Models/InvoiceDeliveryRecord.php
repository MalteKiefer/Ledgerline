<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceDeliveryRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_invoice_deliveries';

    protected $fillable = ['recipient'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'invoice_id' => 'integer', 'document_series_id' => 'integer',
            'document_revision_id' => 'integer', 'attempts' => 'integer',
            'queued_at' => 'immutable_datetime', 'last_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime', 'next_retry_at' => 'immutable_datetime',
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
        throw new \LogicException('Invoice delivery state may change only through repository compare-and-set writes.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Invoice delivery state may change only through repository compare-and-set writes.');
    }

    /** @return GuardedMutationBuilder<InvoiceDeliveryRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Invoice delivery state may change only through repository compare-and-set writes.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Invoice delivery state may change only through repository compare-and-set writes.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Invoice deliveries cannot be deleted directly.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Invoice delivery state may change only through repository compare-and-set writes.');
        }
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<InvoiceRecord, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceRecord::class, 'invoice_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'document_revision_id');
    }
}
