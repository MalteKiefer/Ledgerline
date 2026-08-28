<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Prevents Eloquent bulk helpers from silently skipping a record's model-event
 * immutability guard. Raw database queries remain the database layer's concern.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
final class GuardedMutationBuilder extends Builder
{
    /**
     * @param  QueryBuilder  $query
     * @param  Closure(self<TModel>, string, array<int|string, mixed>): void  $guard
     */
    public function __construct($query, private readonly Closure $guard)
    {
        parent::__construct($query);
    }

    /**
     * @template TGuardedModel of Model
     *
     * @param  class-string<TGuardedModel>  $modelClass
     * @param  Closure(self<TGuardedModel>, string, array<int|string, mixed>): void  $guard
     * @return self<TGuardedModel>
     */
    public static function forModel(QueryBuilder $query, string $modelClass, Closure $guard): self
    {
        return new self($query, $guard);
    }

    public function update(array $values)
    {
        $this->guard('update', $values);

        return parent::update($values);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->guard('upsert');

        return parent::upsert($values, $uniqueBy, $update);
    }

    /** @param array<int, string>|string|null $column */
    public function touch($column = null)
    {
        $this->guard('touch');

        return parent::touch($column);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->guard('increment', $this->incrementValues($column, $amount, $extra));

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->guard('decrement', $this->incrementValues($column, $amount, $extra));

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->guard('incrementEach', [...$columns, ...$extra]);

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->guard('decrementEach', [...$columns, ...$extra]);

        return parent::decrementEach($columns, $extra);
    }

    public function delete()
    {
        $this->guard('delete');

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->guard('forceDelete');

        return parent::delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable(bool): array<string, mixed>  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $this->guard('updateOrInsert');

        return $this->toBase()->updateOrInsert($attributes, $values);
    }

    /** @param array<string, mixed> $values */
    public function updateFrom(array $values): int
    {
        $this->guard('updateFrom', $values);

        return $this->toBase()->updateFrom($values);
    }

    public function truncate(): void
    {
        $this->guard('truncate');

        $this->toBase()->truncate();
    }

    /** @param array<int|string, mixed> $values */
    private function guard(string $operation, array $values = []): void
    {
        ($this->guard)($this, $operation, $values);
    }

    /**
     * @param  mixed  $column
     * @param  mixed  $amount
     * @param  array<int|string, mixed>  $extra
     * @return array<int|string, mixed>
     */
    private function incrementValues($column, $amount, array $extra): array
    {
        if (is_string($column)) {
            $extra[$column] = $amount;
        }

        return $extra;
    }
}
