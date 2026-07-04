<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ResourceShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
    /** The column holding the owning user id (override per model, e.g. Photo). */
    public function ownerColumn(): string
    {
        return 'user_id';
    }

    protected static function bootSharesWithUsers(): void
    {
        static::creating(function ($model): void {
            $column = $model->ownerColumn();
            if ($model->{$column} === null && Auth::check()) {
                $model->{$column} = Auth::id();
            }
        });

        // Visible = owned by me OR shared with me.
        static::addGlobalScope('ownerOrShared', function (Builder $query): void {
            if (! Auth::check()) {
                return;
            }
            $model = $query->getModel();
            $query->where(function (Builder $q) use ($model): void {
                $q->where($model->getTable().'.'.$model->ownerColumn(), Auth::id())
                    ->orWhereIn($model->getQualifiedKeyName(), self::sharedIdsFor(Auth::id(), $model->getMorphClass()));
            });
        });

        // Central write guard: a non-owner may only mutate with a write share.
        $guard = function ($model): void {
            if (Auth::check() && $model->exists && ! $model->canEdit(Auth::id())) {
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

    public function shares(): MorphMany
    {
        return $this->morphMany(ResourceShare::class, 'shareable');
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

    public function unshareFrom(User $user): void
    {
        ResourceShare::query()
            ->where('shareable_type', $this->getMorphClass())
            ->where('shareable_id', $this->getKey())
            ->where('shared_with_user_id', $user->id)
            ->delete();
    }
}
