<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The per-record update rule this app applies everywhere: read the row under a
 * lock, refuse the write if someone else moved it, bump the counter.
 *
 * It lives in one place because every module's update path must behave the same
 * way — a second copy would eventually disagree about what a conflict is, and a
 * blind overwrite is the one outcome none of them may produce.
 */
trait OptimisticUpdates
{
    /**
     * Optimistic per-row update inside a transaction. Returns the fresh model,
     * false on version conflict, or null when the row is gone.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $patch
     */
    protected function optimistic(string $modelClass, int $id, array $patch, ?int $expected): Model|false|null
    {
        return DB::transaction(function () use ($modelClass, $id, $patch, $expected): Model|false|null {
            $fresh = $modelClass::query()->lockForUpdate()->find($id);
            if (! $fresh instanceof Model) {
                return null;
            }
            $raw = $fresh->getAttribute('version');
            $ver = is_int($raw) ? $raw : 0;
            if ($expected !== null && $ver !== $expected) {
                return false;
            }
            $fresh->fill($patch);
            $fresh->setAttribute('version', $ver + 1);
            $fresh->save();

            return $fresh;
        });
    }

    /**
     * Turn an {@see optimistic()} result into a JSON response (404 / 409 / 200).
     *
     * The 409 carries the current version so the client can re-fetch and merge
     * rather than guess.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function optimisticJson(Model|false|null $result, string $modelClass, int $id, string $key): JsonResponse
    {
        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = $modelClass::query()->find($id);
            $v = $current instanceof Model ? $current->getAttribute('version') : null;

            return response()->json(['error' => 'version_conflict', 'version' => is_int($v) ? $v : 0], 409);
        }

        return response()->json([$key => $result]);
    }
}
