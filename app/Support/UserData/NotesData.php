<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Note;
use App\Models\ResourceShare;
use App\Models\User;

/**
 * Notes module contribution to per-user GDPR export and account erasure.
 *
 * A note is owned by exactly one user (notes.user_id) and may be shared with
 * other users through polymorphic resource_shares rows. Public share links
 * (note_shares) are anonymous, self-expiring snapshots with no owner column,
 * so they are neither exportable nor purgeable per user and are left to the
 * expiry pruner.
 */
final class NotesData implements UserDataContributor
{
    public function key(): string
    {
        return 'notes';
    }

    public function export(User $user): array
    {
        $morph = (new Note)->getMorphClass();

        return Note::withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(function (Note $note) use ($morph): array {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'content' => $note->content,
                    'tags' => $note->tags ?? [],
                    'pinned' => (bool) $note->pinned,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                    'deleted_at' => $note->deleted_at?->toIso8601String(),
                    'shares' => ResourceShare::query()
                        ->where('shareable_type', $morph)
                        ->where('shareable_id', (string) $note->getKey())
                        ->orderBy('id')
                        ->get()
                        ->map(fn (ResourceShare $share): array => [
                            'shared_with_user_id' => $share->shared_with_user_id,
                            'permission' => $share->permission,
                            'created_at' => $share->created_at?->toIso8601String(),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    public function purge(User $user): void
    {
        $morph = (new Note)->getMorphClass();

        $noteIds = Note::withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        // Drop every share touching this user's notes: shares the user granted
        // as owner, plus any leftover grants keyed to those note ids.
        ResourceShare::query()
            ->where('shareable_type', $morph)
            ->where(function ($query) use ($user, $noteIds): void {
                $query->where('owner_id', $user->id);

                if ($noteIds !== []) {
                    $query->orWhereIn('shareable_id', $noteIds);
                }
            })
            ->delete();

        // Permanently delete the notes themselves (bypassing SoftDeletes).
        Note::withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->forceDelete();
    }
}
