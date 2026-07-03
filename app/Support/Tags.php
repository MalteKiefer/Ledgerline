<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for the free-form tag lists shared by notes, to-dos,
 * bookmarks and files: the validation rules and the normalisation applied
 * before persisting.
 */
final class Tags
{
    /** Maximum length of a single tag. */
    public const MAX_LENGTH = 64;

    /**
     * Validation rules for a `tags` array field, to spread into a validate()
     * call: `$request->validate([..., ...Tags::rules()])`.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'tags' => ['array'],
            'tags.*' => ['string', 'max:'.self::MAX_LENGTH],
        ];
    }

    /**
     * Normalise a submitted tag list to a clean, re-indexed array of strings.
     *
     * @return list<string>
     */
    public static function normalize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return array_values(array_map(static fn ($t): string => (string) $t, $input));
    }
}
