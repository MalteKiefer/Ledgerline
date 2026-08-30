<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RecurringInvoiceTemplateVersionRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_recurring_invoice_template_versions';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'template_id' => 'integer', 'version_number' => 'integer',
            'effective_from' => 'immutable_date', 'draft_snapshot' => 'array', 'created_by' => 'integer',
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
        throw new \LogicException('Recurring invoice template versions are immutable.');
    }

    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Recurring invoice template versions are immutable.');
    }

    /** @return GuardedMutationBuilder<RecurringInvoiceTemplateVersionRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel($query, self::class, static function (
            GuardedMutationBuilder $builder,
            string $operation,
        ): void {
            if (! in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                throw new \LogicException('Recurring invoice template versions are immutable.');
            }
        });
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Recurring invoice template versions are immutable.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Recurring invoice template versions are immutable.');
    }

    private function assertInsert(): void
    {
        if ($this->exists) {
            throw new \LogicException('Recurring invoice template versions are immutable.');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
