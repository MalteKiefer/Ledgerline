<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use Illuminate\Support\Collection;

/**
 * Finds likely-duplicate contacts within a user's address books. Two contacts
 * are grouped when they share a normalised e-mail, a normalised phone number, or
 * an identical full name — grouping is transitive (A~B, B~C ⇒ {A,B,C}), matching
 * how Google Contacts clusters. Detection is computed live from the denormalised
 * columns (contact counts are small); nothing is stored except user dismissals.
 */
class ContactDuplicateFinder
{
    /**
     * @return list<array{signature: string, reasons: list<string>, contacts: list<array<string, mixed>>}>
     */
    public function forUser(int $userId): array
    {
        $bookIds = AddressBook::where('user_id', $userId)->pluck('id');
        $contacts = Contact::query()->whereIn('address_book_id', $bookIds)->get();
        if ($contacts->count() < 2) {
            return [];
        }

        // Union-Find over contact ids (UUID strings). PHP arrays key fine by
        // string, so no numeric coercion — that would collapse all UUIDs.
        $parent = [];
        $find = function (string $x) use (&$parent, &$find): string {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                $x = $parent[$x];
            }

            return $x;
        };
        $union = function (string $a, string $b) use (&$parent, $find): void {
            $parent[$find($a)] = $find($b);
        };

        foreach ($contacts as $c) {
            $parent[(string) $c->id] = (string) $c->id;
        }

        // Map each normalised key → the first contact id that carried it; when a
        // later contact reuses the key, union the two.
        $seen = [];
        foreach ($contacts as $c) {
            foreach ($this->keysFor($c) as $key) {
                if (isset($seen[$key])) {
                    $union((string) $c->id, $seen[$key]);
                } else {
                    $seen[$key] = (string) $c->id;
                }
            }
        }

        /** @var array<string, Collection<int, Contact>> $components */
        $components = $contacts->groupBy(fn (Contact $c): string => $find((string) $c->id));

        $dismissed = ContactDuplicateDismissal::where('user_id', $userId)->pluck('signature')->flip();

        $groups = [];
        foreach ($components as $members) {
            if ($members->count() < 2) {
                continue;
            }
            $ids = $members->pluck('id')->all();
            $signature = ContactDuplicateDismissal::signatureFor($ids);
            if ($dismissed->has($signature)) {
                continue;
            }
            $groups[] = [
                'signature' => $signature,
                'reasons' => $this->reasonsFor($members),
                'contacts' => $members->map(fn (Contact $c): array => $this->row($c))->values()->all(),
            ];
        }

        // Largest groups first — they are the ones worth resolving.
        usort($groups, fn ($a, $b): int => count($b['contacts']) <=> count($a['contacts']));

        return $groups;
    }

    /**
     * Normalised match keys for a contact: e-mail (lowercased), phone (digits,
     * ignoring very short strings), and full name (lowercased).
     *
     * @return list<string>
     */
    private function keysFor(Contact $c): array
    {
        $keys = [];
        foreach ($this->values($c->emails) as $email) {
            $norm = strtolower(trim($email));
            if ($norm !== '') {
                $keys[] = 'e:'.$norm;
            }
        }
        foreach ($this->values($c->phones) as $phone) {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if (strlen($digits) >= 5) {
                $keys[] = 'p:'.$digits;
            }
        }
        $name = $this->normalName($c);
        if ($name !== '') {
            $keys[] = 'n:'.$name;
        }

        return array_values(array_unique($keys));
    }

    /** Full name for matching: "first last" when set, else the formatted name. */
    private function normalName(Contact $c): string
    {
        $name = trim(((string) $c->first_name).' '.((string) $c->last_name));
        if ($name === '') {
            $name = (string) $c->fn;
        }
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));

        // Ignore empty/placeholder names so unrelated blank contacts don't merge.
        return preg_match('/\p{L}/u', $name) && $name !== 'unnamed' ? $name : '';
    }

    /**
     * @param  Collection<int, Contact>  $members
     * @return list<string>
     */
    private function reasonsFor(Collection $members): array
    {
        $count = fn (callable $keyer): array => collect($members)
            ->flatMap($keyer)->filter()->countBy()->filter(fn (int $n): bool => $n > 1)->keys()->all();

        $reasons = [];
        if ($count(fn (Contact $c): array => array_map(fn ($e): string => strtolower(trim($e)), $this->values($c->emails))) !== []) {
            $reasons[] = 'email';
        }
        if ($count(fn (Contact $c): array => array_map(fn ($p): string => (string) preg_replace('/\D+/', '', $p), $this->values($c->phones))) !== []) {
            $reasons[] = 'phone';
        }
        if ($count(fn (Contact $c): array => [$this->normalName($c)]) !== []) {
            $reasons[] = 'name';
        }

        return $reasons;
    }

    /** @return array<string, mixed> */
    private function row(Contact $c): array
    {
        return [
            'id' => $c->id,
            'book' => $c->address_book_id,
            'fn' => $c->fn,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'org' => $c->org,
            'emails' => $this->values($c->emails),
            'phones' => $this->values($c->phones),
            'has_photo' => (bool) $c->has_photo,
            'avatar' => $c->has_photo ? route('contacts.avatar', ['contact' => $c]).'?v='.($c->updated_at?->timestamp ?? 0) : null,
        ];
    }

    /**
     * Denormalised emails/phones are stored either as a plain list or as
     * [{value,type}] — flatten to plain string values.
     *
     * @return list<string>
     */
    private function values(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            $value = is_array($item) ? ($item['value'] ?? '') : $item;
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }
}
