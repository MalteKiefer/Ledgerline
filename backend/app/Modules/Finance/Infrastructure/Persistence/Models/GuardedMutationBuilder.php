<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

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
        return $this->guardedMutation(
            'update',
            $values,
            fn () => parent::update($values),
        );
    }

    /** @param array<int|string, mixed> $values */
    public function insert(array $values): bool
    {
        $this->guard('insert', $values);

        return $this->toBase()->insert($values);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return int<0, max>
     */
    public function insertOrIgnore(array $values): int
    {
        $this->guard('insertOrIgnore', $values);

        return $this->toBase()->insertOrIgnore($values);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  non-empty-array<non-empty-string>  $returning
     * @param  non-empty-string|non-empty-array<non-empty-string>|null  $uniqueBy
     * @return Collection<int, object>
     */
    public function insertOrIgnoreReturning(
        array $values,
        array $returning = ['*'],
        array|string|null $uniqueBy = null,
    ): Collection {
        $this->guard('insertOrIgnoreReturning', $values);

        return $this->toBase()->insertOrIgnoreReturning($values, $returning, $uniqueBy);
    }

    /** @param array<string, mixed> $values */
    public function insertGetId(array $values, ?string $sequence = null): int
    {
        $this->guard('insertGetId', $values);

        return $this->toBase()->insertGetId($values, $sequence);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  Closure|QueryBuilder|Builder<*>|string  $query
     */
    public function insertUsing(array $columns, $query): int
    {
        $this->guard('insertUsing', array_fill_keys($columns, true));

        return $this->toBase()->insertUsing($columns, $query);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  Closure|QueryBuilder|Builder<*>|string  $query
     */
    public function insertOrIgnoreUsing(array $columns, $query): int
    {
        $this->guard('insertOrIgnoreUsing', array_fill_keys($columns, true));

        return $this->toBase()->insertOrIgnoreUsing($columns, $query);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->guard('upsert', $values);

        return parent::upsert($values, $uniqueBy, $update);
    }

    /** @param array<int, string>|string|null $column */
    public function touch($column = null)
    {
        return $this->guardedMutation(
            'touch',
            $this->touchValues($column),
            fn () => parent::touch($column),
        );
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->guardedMutation(
            'increment',
            $this->incrementValues($column, $amount, $extra),
            fn () => parent::increment($column, $amount, $extra),
        );
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->guardedMutation(
            'decrement',
            $this->incrementValues($column, $amount, $extra),
            fn () => parent::decrement($column, $amount, $extra),
        );
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        return $this->guardedMutation(
            'incrementEach',
            [...$columns, ...$extra],
            fn () => parent::incrementEach($columns, $extra),
        );
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        return $this->guardedMutation(
            'decrementEach',
            [...$columns, ...$extra],
            fn () => parent::decrementEach($columns, $extra),
        );
    }

    public function delete()
    {
        return $this->guardedMutation('delete', [], fn () => parent::delete());
    }

    public function forceDelete()
    {
        return $this->guardedMutation('forceDelete', [], fn () => parent::delete());
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
        return $this->guardedMutation(
            'updateFrom',
            $values,
            fn (): int => $this->toBase()->updateFrom($values),
        );
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
     * @template TResult
     *
     * @param  array<int|string, mixed>  $values
     * @param  Closure(): TResult  $mutation
     * @return TResult
     */
    private function guardedMutation(string $operation, array $values, Closure $mutation): mixed
    {
        return $this->getConnection()->transaction(function () use ($operation, $values, $mutation): mixed {
            $this->guard($operation, $values);

            return $mutation();
        });
    }

    /**
     * @param  array<int, string>|string|null  $column
     * @return array<string, true>
     */
    private function touchValues(array|string|null $column): array
    {
        $values = [];

        foreach ((array) $column as $name) {
            $values[$name] = true;
        }

        return $values;
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
