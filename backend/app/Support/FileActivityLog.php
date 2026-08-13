<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileActivity;
use App\Models\FileEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Records Files activity-feed entries. Owner-scoped: `ownerId` is the user
 * whose data changed; `actorId` is whoever performed it (null for anonymous
 * upload-link contributors). Best-effort — never breaks the mutation.
 */
final class FileActivityLog
{
    /** @param array<string,mixed> $meta */
    public static function record(int $ownerId, string $action, ?FileEntry $file = null, array $meta = [], ?int $folderId = null, ?string $actorName = null): void
    {
        try {
            $activity = new FileActivity;
            $activity->forceFill([
                'user_id' => $ownerId,
                'file_id' => $file?->getKey(),
                'file_folder_id' => $folderId ?? $file?->file_folder_id,
                'action' => $action,
                'actor_id' => Auth::id(),
                'actor_name' => $actorName,
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ])->save();
        } catch (\Throwable) {
            // Activity logging is best-effort; a failure must never abort the file op.
        }
    }
}
