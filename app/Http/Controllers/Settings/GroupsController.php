<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin group management (workspace-wide, gated by manage-global-settings): create,
 * edit and delete groups. A group is a reusable limit template (files/gallery quota
 * + device cap) plus a shareable flag. Limits are non-secret metadata — zero-knowledge
 * is unaffected. Membership is assigned per user on the user-management page.
 */
class GroupsController extends Controller
{
    use RedirectsToSettings;

    public function index(Request $request): View
    {
        return view('settings.groups.index', [
            'groups' => Group::withCount('members')->with('members:id,name,email')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = Group::create($this->validated($request));
        $group->members()->sync($this->memberIds($request));

        return $this->savedSettings('groups', 'settings.groups', 'settings.groups_saved');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($this->validated($request, $group->id));
        $group->members()->sync($this->memberIds($request));

        return $this->savedSettings('groups', 'settings.groups', 'settings.groups_saved');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        // Pivot rows cascade; members lose the group's limit contribution but keep
        // their accounts and any per-user overrides.
        $group->delete();

        return $this->savedSettings('groups', 'settings.groups', 'settings.groups_deleted');
    }

    /**
     * A blank limit clears that dimension (the group sets no cap for it).
     *
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
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys((array) config('modules.list', [])))],
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
            'modules' => self::modulesFromRequest($request),
        ];
    }

    /**
     * The submitted module allow-list, or null for "no restriction" (all modules).
     * All-checked collapses to null so newly-added modules stay enabled by default.
     *
     * @return list<string>|null
     */
    public static function modulesFromRequest(Request $request): ?array
    {
        $known = array_keys((array) config('modules.list', []));
        if (! $request->has('modules_marker')) {
            return null;
        }
        $input = $request->input('modules', []);
        $list = is_array($input) ? array_filter($input, 'is_string') : [];
        $checked = array_values(array_intersect($known, $list));

        return count($checked) === count($known) ? null : $checked;
    }

    /**
     * The submitted member user ids (the full set; empty clears membership).
     *
     * @return list<int>
     */
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
