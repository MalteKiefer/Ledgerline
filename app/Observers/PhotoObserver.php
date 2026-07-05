<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Face;
use App\Models\Photo;
use App\Services\Gallery\FaceClusterer;

/**
 * Keeps face clusters ("People") consistent when photos are permanently deleted.
 * A soft delete (trash) leaves faces and people intact so a restore brings the
 * person back; a force delete (empty trash / purge) removes the photo's faces and
 * then recomputes each affected person — a person left with no faces is removed,
 * so deleting all of someone's photos makes that person disappear.
 */
class PhotoObserver
{
    public function forceDeleting(Photo $photo): void
    {
        $personIds = Face::where('photo_id', $photo->id)
            ->whereNotNull('person_id')->pluck('person_id')->unique();

        // Remove this photo's faces now (before recompute); the DB cascade would
        // do the same, but doing it here lets us recount immediately.
        Face::where('photo_id', $photo->id)->delete();

        $clusterer = app(FaceClusterer::class);
        foreach ($personIds as $personId) {
            $clusterer->recompute((string) $personId);
        }
    }
}
