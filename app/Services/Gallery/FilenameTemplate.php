<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Illuminate\Support\Str;

/**
 * Renders a photo's display name from a placeholder template, using its capture
 * date and original name. The original file extension is always preserved.
 *
 * Supported placeholders (wrapped in double braces):
 *   {{y}}    four-digit year        {{yy}}   two-digit year
 *   {{MM}}   month (01-12)          {{dd}}   day (01-31)
 *   {{HH}}   hour (00-23)           {{mm}}   minute (00-59)   {{ss}} second
 *   {{filename}}  original name without extension
 *   {{ext}}  original extension (without the dot)
 */
class FilenameTemplate
{
    /**
     * Build the new display name for a photo, or null when no usable name can be
     * produced (empty template or empty result).
     */
    public function render(Photo $photo, ?string $template): ?string
    {
        $template = trim((string) $template);
        if ($template === '') {
            return null;
        }

        $taken = $photo->taken_at;
        $ext = strtolower(pathinfo((string) $photo->name, PATHINFO_EXTENSION) ?: 'jpg');
        $base = pathinfo((string) $photo->name, PATHINFO_FILENAME);

        $replacements = [
            '{{y}}' => $taken?->format('Y') ?? '',
            '{{yy}}' => $taken?->format('y') ?? '',
            '{{MM}}' => $taken?->format('m') ?? '',
            '{{dd}}' => $taken?->format('d') ?? '',
            '{{HH}}' => $taken?->format('H') ?? '',
            '{{mm}}' => $taken?->format('i') ?? '',
            '{{ss}}' => $taken?->format('s') ?? '',
            '{{filename}}' => $base,
            '{{ext}}' => $ext,
        ];

        $name = strtr($template, $replacements);

        // Drop any extension the template produced; we append the real one below.
        $name = pathinfo($name, PATHINFO_DIRNAME) !== '.'
            ? pathinfo($name, PATHINFO_DIRNAME).'/'.pathinfo($name, PATHINFO_FILENAME)
            : pathinfo($name, PATHINFO_FILENAME);

        // Keep path separators (template may build sub-folders) but sanitise each
        // segment into a safe slug-ish filename.
        $segments = array_filter(array_map(
            static fn (string $s): string => Str::of($s)->trim()->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')->trim('-')->value(),
            explode('/', $name),
        ), static fn (string $s): bool => $s !== '');

        $clean = implode('/', $segments);
        if ($clean === '') {
            return null;
        }

        return $clean.'.'.$ext;
    }
}
