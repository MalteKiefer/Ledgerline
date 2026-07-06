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

        // Postal addresses. ADR parts (RFC 6350): PO box; extended; street;
        // locality; region; postal code; country.
        foreach ($data['addresses'] ?? [] as $a) {
            if (! is_array($a)) {
                continue;
            }
            $parts = [
                '', (string) ($a['ext'] ?? ''), (string) ($a['street'] ?? ''),
                (string) ($a['city'] ?? ''), (string) ($a['region'] ?? ''),
                (string) ($a['zip'] ?? ''), (string) ($a['country'] ?? ''),
            ];
            if (trim(implode('', $parts)) === '') {
                continue;
            }
            $card->add('ADR', $parts, $this->typeParam($a));
        }

        // Related people/contacts. A link to another contact travels as a
        // urn:uuid pointing at that card's UID; free-text names as VALUE=text.
        foreach ($data['related'] ?? [] as $r) {
            if (! is_array($r)) {
                continue;
            }
            $type = trim((string) ($r['type'] ?? ''));
            $uid = trim((string) ($r['uid'] ?? ''));
            $value = trim((string) ($r['value'] ?? ''));
            if ($uid !== '') {
                $card->add('RELATED', 'urn:uuid:'.$uid, $type !== '' ? ['TYPE' => $type] : []);
            } elseif ($value !== '') {
                $params = ['VALUE' => 'text'] + ($type !== '' ? ['TYPE' => $type] : []);
                $card->add('RELATED', $value, $params);
            }
        }

        // Free-form labelled fields, grouped like the anniversaries above so
        // the label survives round-trips (itemN.X-LL-FIELD + itemN.X-ABLabel).
        foreach ($data['custom_fields'] ?? [] as $f) {
            $value = is_array($f) ? trim((string) ($f['value'] ?? '')) : '';
            if ($value === '') {
                continue;
            }
            $group = 'item'.(++$i);
            $card->add($group.'.X-LL-FIELD', $value);
            $card->add($group.'.X-ABLABEL', trim((string) ($f['label'] ?? '')) ?: 'Field');
        }

        if (! empty($data['favorite'])) {
            $card->add('X-LL-FAVORITE', '1');
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
            'photo' => $this->photoUri($card),
            'addresses' => $this->addresses($card),
            'related' => $this->related($card),
            'custom_fields' => $this->customFields($card),
            'favorite' => $this->favorite($card),
        ];
    }

    /**
     * @return list<array{type: ?string, ext: ?string, street: ?string, city: ?string, region: ?string, zip: ?string, country: ?string}>
     */
    private function addresses(VCard $card): array
    {
        $out = [];
        foreach ($card->ADR ?? [] as $adr) {
            $p = $adr->getParts();
            $entry = [
                'type' => $adr['TYPE'] !== null ? (string) $adr['TYPE'] : null,
                'ext' => $this->part($p, 1),
                'street' => $this->part($p, 2),
                'city' => $this->part($p, 3),
                'region' => $this->part($p, 4),
                'zip' => $this->part($p, 5),
                'country' => $this->part($p, 6),
            ];
            if (implode('', array_map('strval', array_diff_key($entry, ['type' => '']))) !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * @return list<array{type: ?string, value: ?string, uid: ?string}>
     */
    private function related(VCard $card): array
    {
        $out = [];
        foreach ($card->RELATED ?? [] as $rel) {
            $raw = trim((string) $rel);
            if ($raw === '') {
                continue;
            }
            $uid = str_starts_with(strtolower($raw), 'urn:uuid:') ? substr($raw, 9) : null;
            $out[] = [
                'type' => $rel['TYPE'] !== null ? (string) $rel['TYPE'] : null,
                'value' => $uid === null ? $raw : null,
                'uid' => $uid,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: ?string, value: string}>
     */
    private function customFields(VCard $card): array
    {
        $out = [];
        foreach ($card->children() as $prop) {
            if (strtoupper($prop->name) !== 'X-LL-FIELD' || ! $prop->group) {
                continue;
            }
            $value = $this->s($prop);
            if ($value === null) {
                continue;
            }
            $label = null;
            foreach ($card->children() as $sibling) {
                if ($sibling->group === $prop->group && strtoupper($sibling->name) === 'X-ABLABEL') {
                    $label = $this->s($sibling);
                    break;
                }
            }
            $out[] = ['label' => $label, 'value' => $value];
        }

        return $out;
    }

    private function favorite(VCard $card): bool
    {
        foreach ($card->children() as $prop) {
            if (strtoupper($prop->name) === 'X-LL-FAVORITE') {
                return trim((string) $prop) === '1';
            }
        }

        return false;
    }

    /**
     * Normalise PHOTO to a data: URI regardless of vCard version. vCard 4.0
     * already carries a data: URI (or an http URL); vCard 3.0 stores a
     * base64/binary body with ENCODING=b and a TYPE param — we wrap that into a
     * data: URI so the app can serve/show it uniformly.
     */
    private function photoUri(VCard $card): ?string
    {
        if (! isset($card->PHOTO)) {
            return null;
        }
        $prop = $card->PHOTO;
        $value = trim((string) $prop);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'data:') || preg_match('#^https?://#i', $value)) {
            return $value;
        }

        // Binary body: infer the mime from the TYPE param (JPEG/PNG/GIF), and
        // make sure the payload is base64 (encode if sabre handed us raw bytes).
        $type = strtolower((string) ($prop['TYPE'] ?? ''));
        $mime = str_contains($type, 'png') ? 'image/png' : (str_contains($type, 'gif') ? 'image/gif' : 'image/jpeg');
        $compact = preg_replace('/\s+/', '', $value) ?? $value;
        $decoded = base64_decode($compact, true);
        $b64 = ($decoded !== false && base64_encode($decoded) === $compact) ? $compact : base64_encode($value);

        return 'data:'.$mime.';base64,'.$b64;
    }

    /**
     * @return array{fn: ?string, first_name: ?string, last_name: ?string, org: ?string, emails: list<string>, phones: list<string>, has_photo: bool, favorite: bool}
     */
    public function denormalize(string $vcard): array
    {
        try {
            $card = Reader::read($vcard, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return ['fn' => null, 'first_name' => null, 'last_name' => null, 'org' => null, 'emails' => [], 'phones' => [], 'has_photo' => false, 'favorite' => false];
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
            'has_photo' => $this->photoUri($card) !== null,
            'favorite' => $this->favorite($card),
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
