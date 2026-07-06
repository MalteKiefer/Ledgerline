<?php

declare(strict_types=1);

namespace App\Services\Bookmarks;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Illuminate\Support\Collection;

/**
 * Reads and writes the Netscape bookmark file format every browser uses for
 * import/export (DL/DT lists, H3 folder headings, A entries with ADD_DATE and
 * a comma-separated TAGS attribute).
 */
class NetscapeBookmarks
{
    public const MAX_IMPORT = 5000;

    /**
     * @param  Collection<int, BookmarkFolder>  $folders
     * @param  Collection<int, Bookmark>  $bookmarks
     */
    public function build(Collection $folders, Collection $bookmarks): string
    {
        $e = fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $line = function ($b) use ($e): string {
            $attrs = 'HREF="'.$e($b->url).'" ADD_DATE="'.($b->created_at?->timestamp ?? 0).'"';
            if (($b->tags ?? []) !== []) {
                $attrs .= ' TAGS="'.$e(implode(',', $b->tags)).'"';
            }
            $out = '        <DT><A '.$attrs.'>'.$e($b->title).'</A>';
            if (filled($b->description)) {
                $out .= "\n        <DD>".$e($b->description);
            }

            return $out;
        };

        $body = '';
        foreach ($folders as $folder) {
            $items = $bookmarks->where('bookmark_folder_id', $folder->id);
            if ($items->isEmpty()) {
                continue;
            }
            $body .= '    <DT><H3 ADD_DATE="'.($folder->created_at?->timestamp ?? 0).'">'.$e($folder->name)."</H3>\n    <DL><p>\n";
            $body .= $items->map($line)->implode("\n")."\n    </DL><p>\n";
        }
        foreach ($bookmarks->whereNull('bookmark_folder_id') as $b) {
            $body .= $line($b)."\n";
        }

        return "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n"
            ."<!-- This is an automatically generated file. -->\n"
            .'<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">'."\n"
            ."<TITLE>Bookmarks</TITLE>\n<H1>Bookmarks</H1>\n<DL><p>\n".$body."</DL><p>\n";
    }

    /**
     * Parse a Netscape bookmark file into flat entries. Nested folders flatten
     * to their nearest heading; only http(s) links survive.
     *
     * @return list<array{folder: ?string, title: string, url: string, tags: list<string>, description: ?string}>
     */
    public function parse(string $html): array
    {
        $doc = new \DOMDocument;
        // The format is intentionally malformed HTML; suppress parser noise.
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $out = [];
        $xpath = new \DOMXPath($doc);

        // Unclosed <DT> tags make the DOM nest entries unpredictably, so a DD's
        // description is resolved by document order: it belongs to the nearest
        // preceding <A>. Map anchor node id => description up front.
        $descriptions = [];
        foreach ($xpath->query('//dd') ?: [] as $dd) {
            $a = $xpath->query('preceding::a[@href][1]', $dd);
            if ($a !== false && $a->length > 0) {
                $text = trim((string) $dd->textContent);
                if ($text !== '') {
                    $descriptions[spl_object_id($a->item(0))] = $text;
                }
            }
        }

        foreach ($xpath->query('//a[@href]') ?: [] as $a) {
            if (count($out) >= self::MAX_IMPORT) {
                break;
            }
            $url = trim((string) $a->getAttribute('href'));
            if (preg_match('#^https?://#i', $url) !== 1 || strlen($url) > 2048) {
                continue;
            }
            $tags = array_values(array_filter(array_map('trim', explode(',', (string) $a->getAttribute('tags')))));

            // Folder = the H3 owning the innermost enclosing <DL>. Unclosed <DT>
            // tags nest entries, so an entry belongs to a folder only when it
            // sits inside a nested list (>1 ancestor <DL>); root-level entries
            // (one ancestor <DL>) have no folder even if an H3 precedes them.
            $folder = null;
            if (($xpath->query('ancestor::dl', $a)->length ?? 0) > 1) {
                $h3 = $xpath->query('ancestor::dl[1]/preceding::h3[1]', $a);
                if ($h3 !== false && $h3->length > 0) {
                    $folder = trim((string) $h3->item(0)->textContent) ?: null;
                }
            }

            $description = $descriptions[spl_object_id($a)] ?? null;

            $out[] = [
                'folder' => $folder !== null ? mb_substr($folder, 0, 120) : null,
                'title' => mb_substr(trim((string) $a->textContent) ?: $url, 0, 255),
                'url' => $url,
                'tags' => array_slice($tags, 0, 20),
                'description' => $description !== null ? mb_substr($description, 0, 20000) : null,
            ];
        }

        return $out;
    }
}
