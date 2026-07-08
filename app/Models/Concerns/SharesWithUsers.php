<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ResourceShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Per-user ownership PLUS cross-user sharing. Like OwnsUserData, but the global
 * scope also reveals rows shared with the current user, and a central write
 * guard rejects edits/deletes by a user who only has read access — so sharing
 * needs no per-controller changes. Outside web auth (queue/console/DAV) nothing
 * is constrained; those paths scope explicitly by the owning record.
 */
trait SharesWithUsers
{
    use AssignsOwner;

    protected static function bootSharesWithUsers(): void
    {
        // Visible = owned by me OR shared with me.
        static::addGlobalScope('ownerOrShared', function (Builder $query): void {
            if (! Auth::check()) {
                return;
            }
            $model = $query->getModel();
            // A zero-knowledge row must NEVER surface via a share (a sharee can't
            // decrypt it and ZK sharing is disabled) — so the shared branch
            // excludes is_encrypted rows for models that carry that column. The
            // owner still sees all of their own rows.
            $zk = array_key_exists('is_encrypted', $model->getCasts());
            $query->where(function (Builder $q) use ($model, $zk): void {
                $q->where($model->getTable().'.'.$model->ownerColumn(), Auth::id())
                    ->orWhere(function (Builder $s) use ($model, $zk): void {
                        $s->whereIn($model->getQualifiedKeyName(), self::sharedIdsFor(Auth::id(), $model->getMorphClass()));
                        if ($zk) {
                            $s->where($model->getTable().'.is_encrypted', false);
                        }
                    });
            });
        });

        // Central write guard: a non-owner may only mutate with a write share —
        // AND never a zero-knowledge (is_encrypted) row, even with a write share:
        // the sharee would re-seal it under THEIR vault key and permanently lock
        // the owner out (irreversible data loss). ZK rows are owner-write-only,
        // enforced server-side (not trusting the sharee's client).
        $guard = function ($model): void {
            if (! Auth::check() || ! $model->exists) {
                return;
            }
            $uid = Auth::id();
            if (! $model->canEdit($uid)) {
                abort(403);
            }
            if (($model->is_encrypted ?? false) && ! $model->isOwnedBy($uid)) {
                abort(403);
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    /** @return Collection<int, mixed> */
    protected static function sharedIdsFor(int $userId, string $morphClass)
    {
        return ResourceShare::query()
            ->where('shared_with_user_id', $userId)
            ->where('shareable_type', $morphClass)
            ->pluck('shareable_id');
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && (int) $this->{$this->ownerColumn()} === $userId;
    }

    public function canEdit(?int $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        return $userId !== null && ResourceShare::query()
            ->where('shareable_type', $this->getMorphClass())
            ->where('shareable_id', $this->getKey())
            ->where('shared_with_user_id', $userId)
            ->where('permission', ResourceShare::WRITE)
            ->exists();
    }

    /** Grant (or update) another user's access. */
    public function shareWith(User $user, string $permission = ResourceShare::READ): ResourceShare
    {
        return ResourceShare::updateOrCreate(
            ['shareable_type' => $this->getMorphClass(), 'shareable_id' => $this->getKey(), 'shared_with_user_id' => $user->id],
            ['owner_id' => $this->{$this->ownerColumn()}, 'permission' => $permission === ResourceShare::WRITE ? ResourceShare::WRITE : ResourceShare::READ],
        );
    }
}
