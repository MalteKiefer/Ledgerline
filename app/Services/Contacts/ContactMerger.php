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
 *
 * VCardService::parse() returns array<string, mixed>, so every field read is
 * narrowed through the helpers below (str/nstr/iter) before use.
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
                'urls' => $this->unionContacts($all, 'urls'),
                'anniversaries' => $this->unionAnniversaries($all),
                'addresses' => $this->unionAddresses($all),
                'related' => $this->unionRelated($all),
                'custom_fields' => $this->unionCustomFields($all),
                // Any duplicate being a favorite keeps the merged card starred.
                'favorite' => $all->contains(fn (Contact $c) => (bool) ($this->vcards->parse($c->vcard)['favorite'] ?? false)),
                // Primary photo wins; otherwise the first duplicate that has one.
                'photo' => $primaryData['photo'] ?? $this->firstPhoto($others),
            ];

            $groupIds = $primary->groups()->pluck('contact_groups.id')
                ->merge($others->flatMap(fn (Contact $c) => $c->groups()->pluck('contact_groups.id')))
                ->map(fn (mixed $id): string => $this->str($id))
                ->filter()->unique()->values()->all();

            $this->writer->update($primary, $merged, $groupIds);

            foreach ($others as $other) {
                $this->writer->delete($other);
            }

            return $primary->fresh() ?? $primary;
        });
    }

    /**
     * @param  Collection<int, Contact>  $all
     */
    private function firstFilled(Collection $all, string $field): ?string
    {
        foreach ($all as $c) {
            $value = $this->vcards->parse($c->vcard)[$field] ?? null;
            if (filled($value)) {
                return $this->str($value);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Contact>  $all
     */
    private function mergeNotes(Collection $all): ?string
    {
        $notes = $all->map(fn (Contact $c) => trim($this->str($this->vcards->parse($c->vcard)['note'] ?? null)))
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
            foreach ($this->iter($this->vcards->parse($c->vcard)[$field] ?? null) as $entry) {
                $value = is_array($entry) ? trim($this->str($entry['value'] ?? null)) : trim($this->str($entry));
                if ($value === '') {
                    continue;
                }
                $key = $field === 'phones' ? preg_replace('/\D+/', '', $value) : strtolower($value);
                if ($key === '' || $key === null || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['value' => $value, 'type' => is_array($entry) ? $this->nstr($entry['type'] ?? null) : null];
            }
        }

        return $out;
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
            foreach ($this->iter($this->vcards->parse($c->vcard)['anniversaries'] ?? null) as $ann) {
                if (! is_array($ann)) {
                    continue;
                }
                $date = trim($this->str($ann['date'] ?? null));
                if ($date === '') {
                    continue;
                }
                $key = $date.'|'.strtolower(trim($this->str($ann['label'] ?? null)));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['date' => $date, 'label' => $this->nstr($ann['label'] ?? null)];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Contact>  $all
     * @return list<array<array-key, ?string>>
     */
    private function unionAddresses(Collection $all): array
    {
        $out = [];
        $seen = [];
        foreach ($all as $c) {
            foreach ($this->iter($this->vcards->parse($c->vcard)['addresses'] ?? null) as $a) {
                if (! is_array($a)) {
                    continue;
                }
                $key = strtolower(implode('|', [
                    $this->str($a['street'] ?? null), $this->str($a['zip'] ?? null),
                    $this->str($a['city'] ?? null), $this->str($a['country'] ?? null),
                ]));
                if (trim($key, '|') === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = array_map(fn (mixed $v): ?string => $this->nstr($v), $a);
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Contact>  $all
     * @return list<array{type: ?string, value: ?string, uid: ?string}>
     */
    private function unionRelated(Collection $all): array
    {
        $out = [];
        $seen = [];
        foreach ($all as $c) {
            foreach ($this->iter($this->vcards->parse($c->vcard)['related'] ?? null) as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $uid = $this->str($r['uid'] ?? null);
                $value = $this->str($r['value'] ?? null);
                $key = strtolower($uid.'|'.$value);
                if ($key === '|' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['type' => $this->nstr($r['type'] ?? null), 'value' => $this->nstr($r['value'] ?? null), 'uid' => $this->nstr($r['uid'] ?? null)];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Contact>  $all
     * @return list<array{label: ?string, value: string}>
     */
    private function unionCustomFields(Collection $all): array
    {
        $out = [];
        $seen = [];
        foreach ($all as $c) {
            foreach ($this->iter($this->vcards->parse($c->vcard)['custom_fields'] ?? null) as $f) {
                if (! is_array($f)) {
                    continue;
                }
                $value = $this->str($f['value'] ?? null);
                if ($value === '') {
                    continue;
                }
                $key = strtolower($this->str($f['label'] ?? null).'|'.$value);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['label' => $this->nstr($f['label'] ?? null), 'value' => $value];
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

    /** Coerce any mixed to a string (empty for non-scalars). */
    private function str(mixed $v): string
    {
        return is_scalar($v) || $v instanceof \Stringable ? (string) $v : '';
    }

    /** Trimmed non-empty string, or null. */
    private function nstr(mixed $v): ?string
    {
        $s = trim($this->str($v));

        return $s !== '' ? $s : null;
    }

    /** @return iterable<mixed> */
    private function iter(mixed $v): iterable
    {
        return is_iterable($v) ? $v : [];
    }
}
