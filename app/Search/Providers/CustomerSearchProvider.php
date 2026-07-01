<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Customer;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Global-search source for customers.
 */
class CustomerSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return 'Customers';
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Customer::query()
            ->where(function ($query) use ($like): void {
                foreach (['name', 'email', 'phone', 'city', 'postal_code', 'street', 'country', 'vat_id'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): SearchResult => new SearchResult(
                group: $this->group(),
                title: $customer->name,
                subtitle: $customer->city,
                url: route('customers.show', $customer),
            ))
            ->all();
    }
}
