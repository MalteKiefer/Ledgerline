<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Album;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for the shareable slug ↔ model-class mapping and
 * owned-resource resolution. Only gallery resources remain shareable — notes,
 * files and folders are zero-knowledge (a recipient can't decrypt them), so
 * they were removed from the registry entirely.
 */
final class Shareable
{
    /** Shareable resource slug → model class (all use SharesWithUsers). */
    private const MAP = [
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
