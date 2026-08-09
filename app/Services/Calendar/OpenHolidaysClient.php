<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Support\OutboundUrl;
use RuntimeException;
use Throwable;

/**
 * Thin, read-only client over the free OpenHolidays API (openholidaysapi.org)
 * for public holidays + school holidays (Ferien) and the country/subdivision
 * lists that drive the SPA selects.
 *
 * The API needs no key. Every request GETs public reference data — it sends NO
 * user PII, only a country/region code and a date range — and goes through the
 * SSRF-guarded {@see OutboundUrl::client()} against the ONE fixed host
 * (openholidaysapi.org); the guard refuses everything else. Failures (timeout,
 * non-2xx, oversized body, malformed JSON) throw a {@see RuntimeException} that
 * the caller catches and degrades from.
 */
class OpenHolidaysClient
{
    /** The single upstream host. OutboundUrl refuses anything else. */
    private const BASE = 'https://openholidaysapi.org';

    private const TIMEOUT = 10;

    /** Refuse an implausibly large reference-data body (defensive cap). */
    private const MAX_BYTES = 4_000_000;

    /**
     * All supported countries.
     *
     * @return list<array{isoCode: string, name: string}>
     */
    public function countries(string $lang = 'EN'): array
    {
        $rows = $this->get('/Countries', ['languageIsoCode' => $this->langCode($lang)]);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $iso = is_string($row['isoCode'] ?? null) ? trim($row['isoCode']) : '';
            if ($iso === '') {
                continue;
            }
            $out[] = ['isoCode' => $iso, 'name' => $this->localizedName($row['name'] ?? null, $lang, $iso)];
        }

        return $out;
    }

    /**
     * Subdivisions (states/regions) of a country — German Bundesländer come back
     * as DE-BW, DE-BY, …
     *
     * @return list<array{code: string, name: string}>
     */
    public function subdivisions(string $country, string $lang = 'EN'): array
    {
        $rows = $this->get('/Subdivisions', [
            'countryIsoCode' => strtoupper(trim($country)),
            'languageIsoCode' => $this->langCode($lang),
        ]);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = is_string($row['code'] ?? null) ? trim($row['code']) : '';
            if ($code === '') {
                continue;
            }
            $out[] = ['code' => $code, 'name' => $this->localizedName($row['name'] ?? null, $lang, $code)];
        }

        return $out;
    }

    /**
     * Public holidays for [from,to] (ISO Y-m-d), optionally scoped to a region.
     * Each is normalized to an all-day date range (single-day when start==end).
     *
     * @return list<array{startDate: string, endDate: string, name: string, allDay: true}>
     */
    public function publicHolidays(string $country, ?string $subdivision, string $from, string $to, string $lang): array
    {
        return $this->holidays('/PublicHolidays', $country, $subdivision, $from, $to, $lang);
    }

    /**
     * School holidays (Ferien) for [from,to] — these are multi-day date ranges.
     *
     * @return list<array{startDate: string, endDate: string, name: string, allDay: true}>
     */
    public function schoolHolidays(string $country, ?string $subdivision, string $from, string $to, string $lang): array
    {
        return $this->holidays('/SchoolHolidays', $country, $subdivision, $from, $to, $lang);
    }

    /**
     * @return list<array{startDate: string, endDate: string, name: string, allDay: true}>
     */
    private function holidays(string $path, string $country, ?string $subdivision, string $from, string $to, string $lang): array
    {
        $params = [
            'countryIsoCode' => strtoupper(trim($country)),
            'validFrom' => $from,
            'validTo' => $to,
            'languageIsoCode' => $this->langCode($lang),
        ];
        $subdivision = $subdivision !== null ? trim($subdivision) : '';
        if ($subdivision !== '') {
            $params['subdivisionCode'] = $subdivision;
        }

        $out = [];
        foreach ($this->get($path, $params) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $start = is_string($row['startDate'] ?? null) ? trim($row['startDate']) : '';
            if ($start === '') {
                continue;
            }
            $end = is_string($row['endDate'] ?? null) && trim($row['endDate']) !== '' ? trim($row['endDate']) : $start;
            $name = $this->localizedName($row['name'] ?? null, $lang, '');
            if ($name === '') {
                continue;
            }
            $out[] = ['startDate' => $start, 'endDate' => $end, 'name' => $name, 'allDay' => true];
        }

        return $out;
    }

    /**
     * GET a JSON array from the fixed OpenHolidays host through the SSRF guard.
     *
     * @param  array<string, string>  $query
     * @return array<int, mixed>
     */
    private function get(string $path, array $query): array
    {
        $url = self::BASE.$path;
        try {
            $response = OutboundUrl::client($url, self::TIMEOUT)
                ->accept('application/json')
                ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)'])
                ->get($url, $query);
        } catch (Throwable $e) {
            throw new RuntimeException('OpenHolidays request failed.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenHolidays returned HTTP '.$response->status().'.');
        }
        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException('OpenHolidays response too large.');
        }

        $json = $response->json();

        return is_array($json) ? array_values($json) : [];
    }

    /**
     * Pick the requested language from an OpenHolidays localized-name array
     * ([{language, text}, …]); fall back to the first entry, then to $fallback.
     */
    private function localizedName(mixed $names, string $lang, string $fallback): string
    {
        if (! is_array($names)) {
            return $fallback;
        }
        $want = $this->langCode($lang);
        $first = null;
        foreach ($names as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $text = is_string($entry['text'] ?? null) ? trim($entry['text']) : '';
            if ($text === '') {
                continue;
            }
            $first ??= $text;
            $language = $entry['language'] ?? null;
            if (is_string($language) && strtoupper($language) === $want) {
                return $text;
            }
        }

        return $first ?? $fallback;
    }

    /** Normalize a UI locale to the 2-letter uppercase code OpenHolidays expects. */
    private function langCode(string $lang): string
    {
        $code = strtoupper(substr(trim($lang), 0, 2));

        return $code !== '' ? $code : 'EN';
    }
}
