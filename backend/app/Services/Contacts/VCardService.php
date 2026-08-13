<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use Illuminate\Support\Str;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Builds and parses vCard 4.0. The raw vCard is the source of truth; build()
 * produces it from the editor's fields, parse() reads it back for the editor,
 * and denormalize() mirrors a few fields into the contacts table for list/search.
 *
 * sabre/vobject is untyped (every property access is mixed), so reads funnel
 * through the small mixed-narrowing helpers below (str/arr/iter/s/part/parts)
 * and Property instances are confirmed with instanceof before use.
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
        $uidValue = $uid !== null && $uid !== '' ? $uid : (string) Str::uuid();
        $card = new VCard(['VERSION' => '4.0', 'UID' => $uidValue]);
        $first = $this->str($data['first_name'] ?? null);
        $last = $this->str($data['last_name'] ?? null);
        $fn = $this->str($data['fn'] ?? null);
        if ($fn === '') {
            $fn = trim($first.' '.$last);
        }
        $card->add('FN', $fn !== '' ? $fn : 'Unnamed');
        $card->add('N', [$last, $first, '', '', '']);

        foreach (['org' => 'ORG', 'title' => 'TITLE', 'nickname' => 'NICKNAME', 'bday' => 'BDAY', 'note' => 'NOTE'] as $key => $prop) {
            if (filled($data[$key] ?? null)) {
                $card->add($prop, $this->str($data[$key] ?? null));
            }
        }

        // A contact may have several anniversaries / important dates. vCard's
        // ANNIVERSARY is single-valued, so store each as an Apple-style grouped
        // itemN.X-ABDATE + itemN.X-ABLabel (widely interoperable).
        $i = 0;
        foreach ($this->iter($data['anniversaries'] ?? null) as $ann) {
            $value = is_array($ann) ? $this->str($ann['date'] ?? null) : $this->str($ann);
            if ($value === '') {
                continue;
            }
            $label = is_array($ann) ? trim($this->str($ann['label'] ?? null)) : '';
            $group = 'item'.(++$i);
            $card->add($group.'.X-ABDATE', $value, ['VALUE' => 'DATE']);
            $card->add($group.'.X-ABLABEL', $label !== '' ? $label : 'Anniversary');
        }

        // Postal addresses. ADR parts (RFC 6350): PO box; extended; street;
        // locality; region; postal code; country.
        foreach ($this->iter($data['addresses'] ?? null) as $a) {
            if (! is_array($a)) {
                continue;
            }
            $parts = [
                '', $this->str($a['ext'] ?? null), $this->str($a['street'] ?? null),
                $this->str($a['city'] ?? null), $this->str($a['region'] ?? null),
                $this->str($a['zip'] ?? null), $this->str($a['country'] ?? null),
            ];
            if (trim(implode('', $parts)) === '') {
                continue;
            }
            $card->add('ADR', $parts, $this->typeParam($a));
        }

        // Related people/contacts. A link to another contact travels as a
        // urn:uuid pointing at that card's UID; free-text names as VALUE=text.
        foreach ($this->iter($data['related'] ?? null) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $type = trim($this->str($r['type'] ?? null));
            $relUid = trim($this->str($r['uid'] ?? null));
            $value = trim($this->str($r['value'] ?? null));
            if ($relUid !== '') {
                $card->add('RELATED', 'urn:uuid:'.$relUid, $type !== '' ? ['TYPE' => $type] : []);
            } elseif ($value !== '') {
                $params = ['VALUE' => 'text'] + ($type !== '' ? ['TYPE' => $type] : []);
                $card->add('RELATED', $value, $params);
            }
        }

        // Free-form labelled fields, grouped like the anniversaries above so
        // the label survives round-trips (itemN.X-LL-FIELD + itemN.X-ABLabel).
        foreach ($this->iter($data['custom_fields'] ?? null) as $f) {
            $value = is_array($f) ? trim($this->str($f['value'] ?? null)) : '';
            if ($value === '') {
                continue;
            }
            $group = 'item'.(++$i);
            $card->add($group.'.X-LL-FIELD', $value);
            $card->add($group.'.X-ABLABEL', trim($this->str(is_array($f) ? ($f['label'] ?? null) : null)) ?: 'Field');
        }

        if (! empty($data['favorite'])) {
            $card->add('X-LL-FAVORITE', '1');
        }

        foreach ($this->iter($data['emails'] ?? null) as $e) {
            $value = is_array($e) ? $this->str($e['value'] ?? null) : $this->str($e);
            if ($value !== '') {
                $card->add('EMAIL', $value, $this->typeParam($e));
            }
        }
        foreach ($this->iter($data['phones'] ?? null) as $p) {
            $value = is_array($p) ? $this->str($p['value'] ?? null) : $this->str($p);
            if ($value !== '') {
                $card->add('TEL', $value, $this->typeParam($p));
            }
        }
        foreach ($this->iter($data['urls'] ?? null) as $u) {
            $value = is_array($u) ? $this->str($u['value'] ?? null) : $this->str($u);
            if ($value !== '') {
                $card->add('URL', $value, $this->typeParam($u));
            }
        }

        $categories = array_values(array_filter(array_map(
            fn (mixed $c): string => trim($this->str($c)),
            $this->arr($data['categories'] ?? null),
        )));
        if ($categories !== []) {
            $card->add('CATEGORIES', $categories);
        }

        // vCard 4.0 PHOTO holds a data: URI directly.
        if (filled($data['photo'] ?? null)) {
            $card->add('PHOTO', $this->str($data['photo'] ?? null));
        }

        $serialized = $card->serialize();

        return is_string($serialized) ? $serialized : '';
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
        if (! $card instanceof VCard) {
            return ['fn' => null, 'emails' => [], 'phones' => [], 'urls' => [], 'categories' => []];
        }

        $n = $this->parts($card, 'N');

        return [
            'uid' => $this->s($card->UID ?? null),
            'fn' => $this->s($card->FN ?? null),
            'last_name' => $this->part($n, 0),
            'first_name' => $this->part($n, 1),
            'org' => $this->orgOf($card),
            'title' => $this->s($card->TITLE ?? null),
            'nickname' => $this->s($card->NICKNAME ?? null),
            'bday' => $this->s($card->BDAY ?? null),
            'anniversaries' => $this->anniversaries($card),
            'note' => $this->s($card->NOTE ?? null),
            'emails' => $this->multi($card, $this->iter($card->EMAIL ?? null)),
            'phones' => $this->multi($card, $this->iter($card->TEL ?? null)),
            'urls' => $this->multi($card, $this->iter($card->URL ?? null)),
            'categories' => $this->parts($card, 'CATEGORIES'),
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
        foreach ($this->iter($card->ADR ?? null) as $adr) {
            if (! $adr instanceof Property) {
                continue;
            }
            $p = array_map(fn (mixed $x): string => $this->str($x), $this->arr($adr->getParts()));
            $entry = [
                'type' => $this->typeOf($card, $adr),
                'ext' => $this->part($p, 1),
                'street' => $this->part($p, 2),
                'city' => $this->part($p, 3),
                'region' => $this->part($p, 4),
                'zip' => $this->part($p, 5),
                'country' => $this->part($p, 6),
            ];
            $entry = $this->splitPackedStreet($entry);
            if (implode('', array_map('strval', array_diff_key($entry, ['type' => '']))) !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Apple/Google exports often pack the whole address into the street
     * component as newline-separated lines ("Street\nCity\nZip\nCountry")
     * leaving every other component empty. Split that back into structured
     * fields so the editor and the geocoder get usable values.
     *
     * @param  array{type: ?string, ext: ?string, street: ?string, city: ?string, region: ?string, zip: ?string, country: ?string}  $entry
     * @return array{type: ?string, ext: ?string, street: ?string, city: ?string, region: ?string, zip: ?string, country: ?string}
     */
    private function splitPackedStreet(array $entry): array
    {
        // Trim stray whitespace/newlines off every component first (Apple often
        // leaves a trailing "\n" on an otherwise structured street).
        foreach (['ext', 'street', 'city', 'region', 'zip', 'country'] as $k) {
            $v = trim((string) ($entry[$k] ?? ''));
            $entry[$k] = $v !== '' ? $v : null;
        }

        $othersEmpty = ($entry['city'] ?? null) === null && ($entry['zip'] ?? null) === null
            && ($entry['region'] ?? null) === null && ($entry['country'] ?? null) === null;

        // The whole address may be packed (newline-separated) into either the
        // street OR the extended component (Apple exports use ext). Only unpack
        // when the granular fields are empty; otherwise keep the first line.
        $street = (string) ($entry['street'] ?? '');
        $ext = (string) ($entry['ext'] ?? '');
        $blob = str_contains($street, "\n") ? $street : (str_contains($ext, "\n") ? $ext : '');

        if (! $othersEmpty || $blob === '') {
            // A structured street that still carries an embedded newline: keep
            // only its first line (the rest duplicates the granular fields).
            if (str_contains($street, "\n")) {
                $entry['street'] = trim(explode("\n", $street)[0]) ?: null;
            }
            // A single-line street parked in the extended component with no
            // street of its own (Apple quirk) → promote it to street.
            if ($othersEmpty && ($entry['street'] ?? null) === null && ($entry['ext'] ?? null) !== null) {
                $entry['street'] = $entry['ext'];
                $entry['ext'] = null;
            }

            return $entry;
        }

        if ($blob === $ext) {
            $entry['ext'] = null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $blob) ?: [])));
        if (count($lines) < 2) {
            $entry['street'] = $lines[0] ?? null;

            return $entry;
        }

        $entry['street'] = array_shift($lines);

        // A trailing line without digits reads as the country.
        if ($lines !== [] && ! preg_match('/\d/', end($lines))) {
            $entry['country'] = array_pop($lines);
        }

        $cityParts = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\d{3,10})\s+(.+)$/u', $line, $m)) {
                // "12345 City" on one line.
                $entry['zip'] = $m[1];
                $cityParts[] = $m[2];
            } elseif (preg_match('/^\d{3,10}(-\d+)?$/', $line)) {
                $entry['zip'] = $line;
            } else {
                $cityParts[] = $line;
            }
        }
        $entry['city'] = $cityParts !== [] ? implode(', ', $cityParts) : null;

        return $entry;
    }

    /**
     * @return list<array{type: ?string, value: ?string, uid: ?string}>
     */
    private function related(VCard $card): array
    {
        $out = [];
        foreach ($this->iter($card->RELATED ?? null) as $rel) {
            if (! $rel instanceof Property) {
                continue;
            }
            $raw = trim((string) $rel);
            if ($raw === '') {
                continue;
            }
            $uid = str_starts_with(strtolower($raw), 'urn:uuid:') ? substr($raw, 9) : null;
            $out[] = [
                'type' => $this->s($rel['TYPE'] ?? null),
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
        foreach ($this->iter($card->children()) as $prop) {
            if (! $prop instanceof Property || strtoupper($this->str($prop->name)) !== 'X-LL-FIELD' || ! $prop->group) {
                continue;
            }
            $value = $this->s($prop);
            if ($value === null) {
                continue;
            }
            $out[] = ['label' => $this->labelFor($card, (string) $prop->group), 'value' => $value];
        }

        return $out;
    }

    /** The itemN.X-ABLABEL sibling text for a grouped property, if any. */
    private function labelFor(VCard $card, string $group): ?string
    {
        foreach ($this->iter($card->children()) as $sibling) {
            if ($sibling instanceof Property && $sibling->group === $group
                && strtoupper($this->str($sibling->name)) === 'X-ABLABEL') {
                return $this->appleLabel($this->s($sibling));
            }
        }

        return null;
    }

    /** Unwrap Apple's "_$!<Work>!$_" label encoding to its inner word. */
    private function appleLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }
        if (preg_match('/^_\$!<(.+)>!\$_$/', $label, $m) === 1) {
            return trim($m[1]);
        }

        return $label;
    }

    /**
     * The TYPE of a multi-instance property: the TYPE parameter, or — for
     * Apple's itemN-grouped exports that carry no TYPE — the group's X-ABLabel.
     */
    private function typeOf(VCard $card, Property $prop): ?string
    {
        $type = $this->s($prop['TYPE'] ?? null);
        if ($type !== null) {
            return $type;
        }

        return $prop->group ? $this->labelFor($card, (string) $prop->group) : null;
    }

    private function favorite(VCard $card): bool
    {
        foreach ($this->iter($card->children()) as $prop) {
            if ($prop instanceof Property && strtoupper($this->str($prop->name)) === 'X-LL-FAVORITE') {
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
        $prop = $card->PHOTO ?? null;
        if (! $prop instanceof Property) {
            return null;
        }
        $value = trim((string) $prop);
        if ($value === '') {
            return null;
        }
        // Already an inline image → use it directly.
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }
        // A remote URL (e.g. Google's auth-gated lh3.googleusercontent.com photo)
        // can't be served through the sandboxed avatar route and usually isn't
        // publicly fetchable — treat it as "no local photo" so the UI falls back
        // to initials instead of showing a broken image.
        if (preg_match('#^(https?|data):#i', $value)) {
            return null;
        }

        // Inline binary body (vCard 3.0 "ENCODING=b" base64, e.g. Apple). Accept
        // it only if it is real base64 of a plausibly-sized image; sniff the mime
        // from the decoded bytes rather than trusting the TYPE param. Never
        // re-encode a non-base64 string (that produced garbage data URIs before).
        $compact = preg_replace('/\s+/', '', $value) ?? $value;
        $decoded = base64_decode($compact, true);
        if ($decoded === false || strlen($decoded) < 100) {
            return null;
        }
        $mime = $this->sniffImageMime($decoded);
        if ($mime === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.$compact;
    }

    /** Detect an image mime from magic bytes (JPEG/PNG/GIF/WebP), else null. */
    private function sniffImageMime(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    /**
     * @return array{fn: ?string, first_name: ?string, last_name: ?string, org: ?string, emails: list<string>, phones: list<string>, has_photo: bool, favorite: bool}
     */
    public function denormalize(string $vcard): array
    {
        try {
            $card = Reader::read($vcard, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return ['fn' => null, 'first_name' => null, 'last_name' => null, 'org' => null, 'emails' => [], 'phones' => [], 'has_photo' => false, 'favorite' => false, 'bday' => null];
        }
        if (! $card instanceof VCard) {
            return ['fn' => null, 'first_name' => null, 'last_name' => null, 'org' => null, 'emails' => [], 'phones' => [], 'has_photo' => false, 'favorite' => false, 'bday' => null];
        }

        $n = $this->parts($card, 'N');

        return [
            'uid' => $this->s($card->UID ?? null),
            'fn' => $this->s($card->FN ?? null),
            'last_name' => $this->part($n, 0),
            'first_name' => $this->part($n, 1),
            'org' => $this->orgOf($card),
            'emails' => $this->values($this->iter($card->EMAIL ?? null)),
            'phones' => $this->values($this->iter($card->TEL ?? null)),
            'has_photo' => $this->photoUri($card) !== null,
            'favorite' => $this->favorite($card),
            'bday' => $this->birthMonthDay($card),
        ];
    }

    /**
     * The BDAY reduced to a year-agnostic "MM-DD" for a cheap birthday match, or
     * null. Handles YYYYMMDD / YYYY-MM-DD / --MMDD / --MM-DD (with or without
     * separators + a trailing time), validating the month/day ranges.
     */
    private function birthMonthDay(VCard $card): ?string
    {
        $raw = $this->s($card->BDAY ?? null);
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        // A year-less form ("--05-01") carries just MMDD; a full date carries YYYYMMDD.
        if (str_starts_with(ltrim($raw), '--') || strlen($digits) === 4) {
            $mmdd = substr($digits, 0, 4);
        } elseif (strlen($digits) >= 8) {
            $mmdd = substr($digits, 4, 4);
        } else {
            return null;
        }
        if (strlen($mmdd) !== 4) {
            return null;
        }
        $month = (int) substr($mmdd, 0, 2);
        $day = (int) substr($mmdd, 2, 2);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%02d-%02d', $month, $day);
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
        foreach ($this->iter($card->children()) as $prop) {
            if (! $prop instanceof Property || strtoupper($this->str($prop->name)) !== 'X-ABDATE' || ! $prop->group) {
                continue;
            }
            $date = $this->s($prop);
            if ($date !== null) {
                $out[] = ['date' => $date, 'label' => $this->labelFor($card, (string) $prop->group)];
            }
        }
        $legacy = $card->ANNIVERSARY ?? null;
        if ($legacy instanceof Property && ($date = $this->s($legacy)) !== null) {
            $out[] = ['date' => $date, 'label' => null];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function typeParam(mixed $entry): array
    {
        $type = is_array($entry) ? trim($this->str($entry['type'] ?? null)) : '';

        return $type !== '' ? ['TYPE' => $type] : [];
    }

    /**
     * @param  iterable<mixed>  $props
     * @return list<array{value: string, type: ?string}>
     */
    private function multi(VCard $card, iterable $props): array
    {
        $out = [];
        $seen = [];
        foreach ($props as $prop) {
            if (! $prop instanceof Property) {
                continue;
            }
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }
            $type = $this->typeOf($card, $prop);
            // Drop duplicate values (Apple lists the same number twice) — but
            // keep a typed instance over an earlier untyped one.
            $norm = strtolower(preg_replace('/\s+/', '', $value) ?? $value);
            if (isset($seen[$norm])) {
                if ($type !== null && $out[$seen[$norm]]['type'] === null) {
                    $out[$seen[$norm]]['type'] = $type;
                }

                continue;
            }
            $seen[$norm] = count($out);
            $out[] = ['value' => $value, 'type' => $type];
        }

        return array_values($out);
    }

    /** Company (+ department) from a structured ORG, without trailing empties. */
    private function orgOf(VCard $card): ?string
    {
        $prop = $card->ORG ?? null;
        if (! $prop instanceof Property) {
            return null;
        }
        $parts = array_values(array_filter(array_map(
            fn (mixed $x): string => trim($this->str($x)),
            $this->arr($prop->getParts()),
        )));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * @param  iterable<mixed>  $props
     * @return list<string>
     */
    private function values(iterable $props): array
    {
        $out = [];
        foreach ($props as $prop) {
            if ($prop instanceof Property) {
                $out[] = trim((string) $prop);
            }
        }

        return $out;
    }

    /**
     * Concrete parts of a single-valued property (e.g. N, CATEGORIES).
     *
     * @return array<int, string>
     */
    private function parts(VCard $card, string $name): array
    {
        $prop = $card->$name ?? null;
        if (! $prop instanceof Property) {
            return [];
        }

        return array_map(fn (mixed $x): string => $this->str($x), $this->arr($prop->getParts()));
    }

    private function s(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /** @param array<int, string> $parts */
    private function part(array $parts, int $i): ?string
    {
        $value = isset($parts[$i]) ? trim($parts[$i]) : '';

        return $value !== '' ? $value : null;
    }

    /** Coerce any mixed to a trimmed-free string (empty for non-scalars). */
    private function str(mixed $v): string
    {
        return is_scalar($v) || $v instanceof \Stringable ? (string) $v : '';
    }

    /** @return array<int, mixed> */
    private function arr(mixed $v): array
    {
        return is_array($v) ? array_values($v) : [];
    }

    /** @return iterable<mixed> */
    private function iter(mixed $v): iterable
    {
        return is_iterable($v) ? $v : [];
    }
}
