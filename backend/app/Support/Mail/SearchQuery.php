<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Support\Carbon;

/**
 * Parses a search box into structured terms.
 *
 * Pure: string in, terms out. No database, no request — so the grammar can be
 * tested against what people actually type instead of being guessed at.
 *
 * Grammar: `field:value` for the fields below, everything else is free text.
 * A value may be quoted ("kiefer networks") to keep its spaces. An unknown
 * field is NOT treated as a field — `foo:bar` stays free text, because a typo
 * must not silently narrow the search to nothing.
 *
 * Note on the naming collision with the listing's own query parameters: the
 * request has `?from=`/`?to=` for the DATE range, while `from:`/`to:` in the
 * search box mean the ADDRESS. Both stay as they are — the parameters are the
 * published contract, and inside a search box `from:` can only sensibly mean
 * the sender. Dates in the box are `before:`/`after:`.
 */
final class SearchQuery
{
    /** Address and text fields, `field:value`. */
    private const TEXT_FIELDS = ['from', 'to', 'subject', 'folder'];

    /** `is:` flags => [column, value]. */
    private const FLAGS = [
        'unread' => ['seen', false],
        'read' => ['seen', true],
        'starred' => ['flagged', true],
        'flagged' => ['flagged', true],
        'unstarred' => ['flagged', false],
        'answered' => ['answered', true],
        'unanswered' => ['answered', false],
        'spam' => ['spam', true],
        'encrypted' => ['encrypted', true],
    ];

    /**
     * @param  array<string, list<string>>  $text  field => values (AND across, a repeated field is AND too)
     * @param  array<string, bool>  $flags  column => expected value
     */
    private function __construct(
        public readonly string $free,
        public readonly array $text,
        public readonly array $flags,
        public readonly ?bool $hasAttachment,
        public readonly ?Carbon $before,
        public readonly ?Carbon $after,
    ) {}

    public static function parse(string $input): self
    {
        $free = [];
        $text = [];
        $flags = [];
        $hasAttachment = null;
        $before = null;
        $after = null;

        foreach (self::tokenise($input) as $token) {
            [$field, $value] = self::split($token);

            if ($field === null || $value === '') {
                $free[] = $token;

                continue;
            }

            if (in_array($field, self::TEXT_FIELDS, true)) {
                $text[$field][] = $value;

                continue;
            }

            if ($field === 'has') {
                // `has:attachment` and the obvious near-misses people type.
                if (in_array($value, ['attachment', 'attachments', 'anhang', 'file'], true)) {
                    $hasAttachment = true;

                    continue;
                }
                $free[] = $token;

                continue;
            }

            if ($field === 'is') {
                $flag = self::FLAGS[$value] ?? null;
                if ($flag === null) {
                    $free[] = $token;

                    continue;
                }
                [$column, $expected] = $flag;
                $flags[$column] = $expected;

                continue;
            }

            if ($field === 'before' || $field === 'after') {
                $date = self::date($value);
                if ($date === null) {
                    $free[] = $token;

                    continue;
                }
                // Inclusive on both ends: someone typing after:2026-01-01 means
                // "from that day on", not "from the day after".
                if ($field === 'before') {
                    $before = $date->endOfDay();
                } else {
                    $after = $date->startOfDay();
                }

                continue;
            }

            $free[] = $token; // unknown field: leave it as text
        }

        return new self(
            trim(implode(' ', $free)),
            $text,
            $flags,
            $hasAttachment,
            $before,
            $after,
        );
    }

    /** True when nothing was recognised and nothing was typed. */
    public function isEmpty(): bool
    {
        return $this->free === ''
            && $this->text === []
            && $this->flags === []
            && $this->hasAttachment === null
            && $this->before === null
            && $this->after === null;
    }

    /**
     * Splits on whitespace, but keeps a quoted run together — including when the
     * quote opens after the colon (`subject:"jahres abschluss"`), which is how
     * every mail client behaves and therefore how people type.
     *
     * @return list<string>
     */
    private static function tokenise(string $input): array
    {
        $tokens = [];
        $current = '';
        $inQuotes = false;

        foreach (mb_str_split(trim($input)) as $char) {
            if ($char === '"') {
                $inQuotes = ! $inQuotes;

                continue;
            }
            if (! $inQuotes && preg_match('/\s/u', $char) === 1) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }
            $current .= $char;
        }
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * `field:value` => [field, value]; anything else => [null, token].
     *
     * The field must look like a field: letters only, and a colon that is not at
     * the start. That keeps a bare URL or a time (`10:30`) out of the grammar.
     *
     * @return array{0: string|null, 1: string}
     */
    private static function split(string $token): array
    {
        if (preg_match('/^([A-Za-z]{2,12}):(.*)$/u', $token, $m) !== 1) {
            return [null, $token];
        }

        return [mb_strtolower($m[1]), trim($m[2])];
    }

    private static function date(string $value): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])
                : null;
        }
        // 31.12.2026 — day first, as German documents write it.
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])
                : null;
        }

        return null;
    }
}
