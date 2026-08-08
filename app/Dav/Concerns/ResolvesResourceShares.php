<?php

declare(strict_types=1);

namespace App\Dav\Concerns;

use App\Models\ResourceShare;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a principal's share level on a DAV collection they don't own. Shared
 * by the CalDAV and CardDAV backends so the polymorphic ResourceShare lookup
 * lives in one place instead of being copied per collection type.
 */
trait ResolvesResourceShares
{
    /**
     * 'read' | 'write' | null — this user's share level on a resource of the
     * given model class that they don't own.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function shareLevel(string $modelClass, string $shareableId, int $userId): ?string
    {
        return ResourceShare::query()
            ->where('shareable_type', (new $modelClass)->getMorphClass())
            ->where('shareable_id', $shareableId)
            ->where('shared_with_user_id', $userId)
            ->value('permission');
    }
}
