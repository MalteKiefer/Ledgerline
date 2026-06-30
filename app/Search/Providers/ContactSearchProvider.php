<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Contact;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Global-search source for contact persons, including their emails and phones.
 */
class ContactSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return 'Contacts';
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Contact::query()
            ->with('customer')
            ->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereHas('emails', fn ($q) => $q->whereRaw('LOWER(email) LIKE ?', [$like]))
                    ->orWhereHas('phones', fn ($q) => $q->whereRaw('LOWER(phone) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Contact $contact): SearchResult => new SearchResult(
                group: $this->group(),
                title: $contact->name,
                subtitle: $contact->function->label().' · '.$contact->customer->name,
                url: route('contacts.show', $contact),
            ))
            ->all();
    }
}
