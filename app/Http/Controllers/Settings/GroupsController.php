<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'groups' => Group::withCount('members')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Group::create($this->validated($request));

        return $this->savedSettings('groups', 'settings.groups', 'settings.groups_saved');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($this->validated($request, $group->id));

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
}
