<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merges a set of duplicate contacts into one surviving "primary" contact,
 * unioning their fields (Google-style): every e-mail, phone, URL, anniversary
 * and group is kept, scalar fields fall back to a non-empty value, and the
 * primary's name/photo win. The other contacts are then deleted (with their
 * CardDAV tombstones) so nothing dangles.
 */
class ContactMerger
{
    public function __construct(
        private readonly VCardService $vcards,
        private readonly ContactWriter $writer,
    ) {}

    /**
     * @param  Collection<int, Contact>  $others  the duplicates to fold into $primary
     */
    public function merge(Contact $primary, Collection $others): Contact
    {
        return DB::transaction(function () use ($primary, $others): Contact {
            $primaryData = $this->vcards->parse($primary->vcard);
            $all = collect([$primary])->merge($others);

            $merged = [
                'fn' => $this->firstFilled($all, 'fn'),
                'first_name' => $this->firstFilled($all, 'first_name'),
                'last_name' => $this->firstFilled($all, 'last_name'),
                'org' => $this->firstFilled($all, 'org'),
                'title' => $this->firstFilled($all, 'title'),
                'nickname' => $this->firstFilled($all, 'nickname'),
                'bday' => $this->firstFilled($all, 'bday'),
                'note' => $this->mergeNotes($all),
                'emails' => $this->unionContacts($all, 'emails'),
                'phones' => $this->unionContacts($all, 'phones'),
                'urls' => $this->unionUrls($all),
                'anniversaries' => $this->unionAnniversaries($all),
                // Primary photo wins; otherwise the first duplicate that has one.
                'photo' => $primaryData['photo'] ?? $this->firstPhoto($others),
            ];

            $groupIds = $primary->groups()->pluck('contact_groups.id')
                ->merge($others->flatMap(fn (Contact $c) => $c->groups()->pluck('contact_groups.id')))
                ->unique()->values()->all();

            $this->writer->update($primary, $merged, $groupIds);

            foreach ($others as $other) {
                $this->writer->delete($other);
            }

            return $primary->fresh();
        });
    }

    /**
     * @param  Collection<int, Contact>  $all
     */
    private function firstFilled(Collection $all, string $field): ?string
    {
        foreach ($all as $c) {
            $data = $this->vcards->parse($c->vcard);
            if (filled($data[$field] ?? null)) {
                return (string) $data[$field];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Contact>  $all
     */
    private function mergeNotes(Collection $all): ?string
    {
        $notes = $all->map(fn (Contact $c) => trim((string) ($this->vcards->parse($c->vcard)['note'] ?? '')))
            ->filter()->unique()->values();

        return $notes->isEmpty() ? null : $notes->implode("\n\n");
    }

    /**
     * Union of {value,type} entries, de-duplicated by normalised value.
     *
     * @param  Collection<int, Contact>  $all
     * @return list<array{value: string, type: ?string}>
     */
    private function unionContacts(Collection $all, string $field): array
    {
        $out = [];
        $seen = [];
        foreach ($all as $c) {
            foreach ($this->vcards->parse($c->vcard)[$field] ?? [] as $entry) {
                $value = is_array($entry) ? trim((string) ($entry['value'] ?? '')) : trim((string) $entry);
                if ($value === '') {
                    continue;
                }
                $key = $field === 'phones' ? preg_replace('/\D+/', '', $value) : strtolower($value);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['value' => $value, 'type' => is_array($entry) ? ($entry['type'] ?? null) : null];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Contact>  $all
     * @return list<string>
     */
    private function unionUrls(Collection $all): array
    {
        return $all->flatMap(fn (Contact $c) => $this->vcards->parse($c->vcard)['urls'] ?? [])
            ->map(fn ($u) => trim((string) $u))->filter()->unique()->values()->all();
    }

    /**
     * @param  Collection<int, Contact>  $all
     * @return list<array{date: string, label: ?string}>
     */
    private function unionAnniversaries(Collection $all): array
    {
        $out = [];
        $seen = [];
        foreach ($all as $c) {
            foreach ($this->vcards->parse($c->vcard)['anniversaries'] ?? [] as $ann) {
                $date = trim((string) ($ann['date'] ?? ''));
                if ($date === '') {
                    continue;
                }
                $key = $date.'|'.strtolower(trim((string) ($ann['label'] ?? '')));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['date' => $date, 'label' => $ann['label'] ?? null];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Contact>  $others
     */
    private function firstPhoto(Collection $others): ?string
    {
        foreach ($others as $c) {
            $photo = $this->vcards->parse($c->vcard)['photo'] ?? null;
            if (is_string($photo) && $photo !== '') {
                return $photo;
            }
        }

        return null;
    }
}
