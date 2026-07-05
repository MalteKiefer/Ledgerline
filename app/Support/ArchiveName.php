<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared helpers for generating collision-free archive (zip/tar) entry names.
 *
 * Every module that streams files into an archive needs the same "if this
 * entry name was already used, append a counter before the extension" logic.
 * This class captures that once so ExportArchiver, GalleryController and
 * MailArchiveController stay in sync.
 */
final class ArchiveName
{
    /**
     * Return an entry name unique within the archive. On the first use the name
     * is returned unchanged; on a collision a counter is appended before the
     * file extension (e.g. "photo.jpg" -> "photo-2.jpg" -> "photo-3.jpg").
     *
     * The extension is detected exactly like the original helpers: the last "."
     * that is not the very first character splits base/extension; a name with no
     * "." (or a leading dot only) is treated as having no extension.
     *
     * The chosen name is recorded in $used (by reference) so subsequent calls
     * see it as taken.
     *
     * @param  array<string, bool>  $used  map of already-used entry names
     * @param  string  $separator  glue placed between base and counter for the
     *                             plain form ("_" or "-")
     * @param  bool  $parenthesize  when true, use the "base (N)ext" form and
     *                              ignore $separator (matches the gallery helper)
     */
    public static function unique(string $name, array &$used, string $separator = '-', bool $parenthesize = false): string
    {
        $dot = strrpos($name, '.');
        [$base, $ext] = $dot > 0 ? [substr($name, 0, $dot), substr($name, $dot)] : [$name, ''];

        $candidate = $name;
        $i = 1;
        while (isset($used[$candidate])) {
            $i++;
            $candidate = $parenthesize
                ? $base.' ('.$i.')'.$ext
                : $base.$separator.$i.$ext;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /**
     * Sanitise a zip member path against Zip-Slip: normalise backslashes, split
     * on "/", scrub each segment (also dropping "", "." and ".."), and rejoin.
     * The result can never contain a ".." segment or a leading "/", so no member
     * can escape the extraction root — however naive the extractor.
     */
    public static function sanitize(string $name): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $name)) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segment = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $segment) ?? 'export';
            $segments[] = trim($segment) !== '' ? trim($segment) : 'export';
        }

        return $segments === [] ? 'export' : implode('/', $segments);
    }
}
