<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin group management over the API (Sanctum device token + manage-global-settings).
 * A group is a reusable limit template (files/gallery quota + device cap) plus a
 * shareable flag; membership is many-to-many. Limits/membership are non-secret
 * metadata — zero-knowledge is unaffected. Mirrors the web Settings/GroupsController.
 */
class GroupController extends Controller
{
    /** List all groups with their members. */
    public function index(Request $request): JsonResponse
    {
        $groups = Group::with('members:id,name,email')->orderBy('name')->get();

        return response()->json(['groups' => $groups->map(fn (Group $g): array => $this->present($g))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = Group::create($this->validated($request));
        $group->members()->sync($this->memberIds($request));

        return response()->json(['group' => $this->present($group->load('members:id,name,email'))], 201);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $group->update($this->validated($request, $group->id));
        $group->members()->sync($this->memberIds($request));

        return response()->json(['group' => $this->present($group->load('members:id,name,email'))]);
    }

    public function destroy(Request $request, Group $group): JsonResponse
    {
        $group->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function present(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'files_quota_mb' => $group->files_quota_mb,
            'gallery_quota_mb' => $group->gallery_quota_mb,
            'max_connected_devices' => $group->max_connected_devices,
            'shareable' => $group->shareable,
            'members' => $group->members->map(fn ($u): array => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:groups,name'.($ignoreId !== null ? ','.$ignoreId : '')],
            'files_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'gallery_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'max_connected_devices' => ['nullable', 'integer', 'min:1', 'max:50'],
            'shareable' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $limit = static fn (string $key): ?int => $request->integer($key) > 0 ? $request->integer($key) : null;

        return [
            'name' => $request->string('name')->value(),
            'files_quota_mb' => $limit('files_quota_mb'),
            'gallery_quota_mb' => $limit('gallery_quota_mb'),
            'max_connected_devices' => $limit('max_connected_devices'),
            'shareable' => $request->boolean('shareable'),
        ];
    }

    /** @return list<int> */
    private function memberIds(Request $request): array
    {
        $ids = $request->input('members', []);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0,
            $ids,
        ), static fn (int $v): bool => $v > 0));
    }
}
