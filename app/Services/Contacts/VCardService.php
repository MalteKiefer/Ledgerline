<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use Illuminate\Support\Str;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Builds and parses vCard 4.0. The raw vCard is the source of truth; build()
 * produces it from the editor's fields, parse() reads it back for the editor,
 * and denormalize() mirrors a few fields into the contacts table for list/search.
 */
class VCardService
{
    /**
     * Build a vCard 4.0 string from editor data. Reuses $uid on update so the
     * card keeps its identity for DAV clients.
     *
     * @param  array<string, mixed>  $data
     */
    public function build(array $data, ?string $uid = null): string
    {
        $card = new VCard(['VERSION' => '4.0']);
        $card->UID = $uid ?: (string) Str::uuid();
        $card->FN = (string) ($data['fn'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: 'Unnamed');
        $card->add('N', [(string) ($data['last_name'] ?? ''), (string) ($data['first_name'] ?? ''), '', '', '']);

        foreach (['org' => 'ORG', 'title' => 'TITLE', 'nickname' => 'NICKNAME', 'bday' => 'BDAY', 'note' => 'NOTE'] as $key => $prop) {
            if (filled($data[$key] ?? null)) {
                $card->add($prop, (string) $data[$key]);
            }
        }

        // A contact may have several anniversaries / important dates. vCard's
        // ANNIVERSARY is single-valued, so store each as an Apple-style grouped
        // itemN.X-ABDATE + itemN.X-ABLabel (widely interoperable).
        $i = 0;
        foreach ($data['anniversaries'] ?? [] as $ann) {
            $value = is_array($ann) ? ($ann['date'] ?? '') : $ann;
            if (! filled($value)) {
                continue;
            }
            $label = is_array($ann) ? trim((string) ($ann['label'] ?? '')) : '';
            $group = 'item'.(++$i);
            $card->add($group.'.X-ABDATE', (string) $value, ['VALUE' => 'DATE']);
            $card->add($group.'.X-ABLABEL', $label !== '' ? $label : 'Anniversary');
        }

        foreach ($data['emails'] ?? [] as $e) {
            $value = is_array($e) ? ($e['value'] ?? '') : $e;
            if (filled($value)) {
                $card->add('EMAIL', (string) $value, $this->typeParam($e));
            }
        }
        foreach ($data['phones'] ?? [] as $p) {
            $value = is_array($p) ? ($p['value'] ?? '') : $p;
            if (filled($value)) {
                $card->add('TEL', (string) $value, $this->typeParam($p));
            }
        }
        foreach ($data['urls'] ?? [] as $u) {
            $value = is_array($u) ? ($u['value'] ?? '') : $u;
            if (filled($value)) {
                $card->add('URL', (string) $value);
            }
        }

        $categories = array_values(array_filter(array_map('trim', (array) ($data['categories'] ?? []))));
        if ($categories !== []) {
            $card->CATEGORIES = $categories;
        }

        // vCard 4.0 PHOTO holds a data: URI directly.
        if (filled($data['photo'] ?? null)) {
            $card->add('PHOTO', (string) $data['photo']);
        }

        return $card->serialize();
    }

    /**
     * Parse a vCard into structured editor data.
     *
     * @return array<string, mixed>
     */
    public function parse(string $vcard): array
    {
        try {
            $card = Reader::read($vcard, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return ['fn' => null, 'emails' => [], 'phones' => [], 'urls' => [], 'categories' => []];
        }

        $n = isset($card->N) ? $card->N->getParts() : [];

        return [
            'uid' => $this->s($card->UID ?? null),
            'fn' => $this->s($card->FN ?? null),
            'last_name' => $this->part($n, 0),
            'first_name' => $this->part($n, 1),
            'org' => $this->s($card->ORG ?? null),
            'title' => $this->s($card->TITLE ?? null),
            'nickname' => $this->s($card->NICKNAME ?? null),
            'bday' => $this->s($card->BDAY ?? null),
            'anniversaries' => $this->anniversaries($card),
            'note' => $this->s($card->NOTE ?? null),
            'emails' => $this->multi($card->EMAIL ?? []),
            'phones' => $this->multi($card->TEL ?? []),
            'urls' => array_map(fn ($u) => trim((string) $u), iterator_to_array($card->URL ?? [])),
            'categories' => isset($card->CATEGORIES) ? $card->CATEGORIES->getParts() : [],
            'photo' => isset($card->PHOTO) ? (string) $card->PHOTO : null,
        ];
    }

    /**
     * @return array{fn: ?string, first_name: ?string, last_name: ?string, org: ?string, emails: list<string>, phones: list<string>, has_photo: bool}
     */
    public function denormalize(string $vcard): array
    {
        try {
            $card = Reader::read($vcard, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return ['fn' => null, 'first_name' => null, 'last_name' => null, 'org' => null, 'emails' => [], 'phones' => [], 'has_photo' => false];
        }

        $n = isset($card->N) ? $card->N->getParts() : [];

        return [
            'uid' => $this->s($card->UID ?? null),
            'fn' => $this->s($card->FN ?? null),
            'last_name' => $this->part($n, 0),
            'first_name' => $this->part($n, 1),
            'org' => $this->s($card->ORG ?? null),
            'emails' => array_map(fn ($e) => trim((string) $e), iterator_to_array($card->EMAIL ?? [])),
            'phones' => array_map(fn ($t) => trim((string) $t), iterator_to_array($card->TEL ?? [])),
            'has_photo' => isset($card->PHOTO),
        ];
    }

    /**
     * All important dates: grouped itemN.X-ABDATE (with itemN.X-ABLabel) plus a
     * legacy single ANNIVERSARY, if present.
     *
     * @return list<array{date: string, label: ?string}>
     */
    private function anniversaries(VCard $card): array
    {
        $out = [];
        foreach ($card->children() as $prop) {
            if (strtoupper($prop->name) !== 'X-ABDATE' || ! $prop->group) {
                continue;
            }
            $label = null;
            foreach ($card->children() as $sibling) {
                if ($sibling->group === $prop->group && strtoupper($sibling->name) === 'X-ABLABEL') {
                    $label = $this->s($sibling);
                    break;
                }
            }
            $date = $this->s($prop);
            if ($date !== null) {
                $out[] = ['date' => $date, 'label' => $label];
            }
        }
        if (isset($card->ANNIVERSARY) && ($date = $this->s($card->ANNIVERSARY)) !== null) {
            $out[] = ['date' => $date, 'label' => null];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function typeParam(mixed $entry): array
    {
        $type = is_array($entry) ? trim((string) ($entry['type'] ?? '')) : '';

        return $type !== '' ? ['TYPE' => $type] : [];
    }

    /** @return list<array{value: string, type: ?string}> */
    private function multi(iterable $props): array
    {
        $out = [];
        foreach ($props as $prop) {
            $type = $prop['TYPE'] !== null ? (string) $prop['TYPE'] : null;
            $out[] = ['value' => trim((string) $prop), 'type' => $type];
        }

        return $out;
    }

    private function s(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** @param array<int, string> $parts */
    private function part(array $parts, int $i): ?string
    {
        $value = isset($parts[$i]) ? trim($parts[$i]) : '';

        return $value !== '' ? $value : null;
    }
}
