<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AddressBook;
use App\Models\Album;
use App\Models\Calendar;
use App\Models\FileFolder;
use App\Models\Note;
use App\Models\Photo;
use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for the shareable slug ↔ model-class mapping (the
 * superset across public + cross-user sharing) and owned-resource resolution.
 *
 * Individual controllers restrict this to their own allowed subset — this
 * registry only knows every possible shareable type, it does not grant any
 * of them; e.g. public sharing must never widen to notes/files.
 */
final class Shareable
{
    /** Shareable resource slug → model class (all use SharesWithUsers). */
    private const MAP = [
        'notes' => Note::class,
        'files' => StoredFile::class,
        'folders' => FileFolder::class,
        'calendars' => Calendar::class,
        'address-books' => AddressBook::class,
        'albums' => Album::class,
        'photos' => Photo::class,
    ];

    /** Resolve the model class for a slug, or null if the slug is unknown. */
    public static function classFor(string $slug): ?string
    {
        return self::MAP[$slug] ?? null;
    }

    /** Reverse lookup: model class → slug, or null if the class is unknown. */
    public static function slugFor(string $class): ?string
    {
        return array_flip(self::MAP)[$class] ?? null;
    }

    /** All known slugs (for Rule::in over the full registry). */
    public static function slugs(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * Resolve a shareable the caller actually OWNS (not merely one shared with
     * them). withoutGlobalScopes so an already-shared-with-me resource can't be
     * re-shared: only the true owner may act on it.
     */
    public static function resolveOwned(string $slug, mixed $id, int $userId): Model
    {
        $class = self::classFor($slug);
        abort_if($class === null, 404);

        $resource = $class::withoutGlobalScopes()->findOrFail($id);
        abort_unless($resource->isOwnedBy($userId), 403);

        return $resource;
    }
}
