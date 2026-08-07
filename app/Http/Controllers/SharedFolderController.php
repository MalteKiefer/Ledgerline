<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FolderShare;
use App\Models\FolderShareMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Owner side of plaintext cross-user folder sharing (pivot). An owner shares one
 * of their own file_folders with another registered user at a role
 * (viewer|editor), lists their shares + members, changes a member's role, removes
 * a member, or deletes a share entirely.
 *
 * FolderShare uses OwnsUserData (owner column `owner_id`), so every query is
 * auto-scoped to the authenticated owner — a stranger's share resolves to 404.
 * Share ids are resolved manually (ownedShare()) with the FolderSharePolicy
 * `manage` check on top, so the owner routes do not depend on route-model
 * binding. Recipients are resolved by exact email only (single identifier
 * per request, unified 422 for "no such user" or self-share — no user directory
 * enumeration).
 */
class SharedFolderController extends Controller
{
    /** All shares the authenticated owner has created, with their members. */
    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        $shares = FolderShare::query()->orderByDesc('id')->get();

        return response()->json([
            'shares' => $shares->map(fn (FolderShare $s): array => $this->shareView($s))->values(),
        ]);
    }

    /** Share a folder with a user (idempotent per folder; adds/updates the recipient). */
    public function store(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file_folder_id' => ['required', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(['viewer', 'editor'])],
        ]);

        $recipient = User::query()->where('email', $request->string('email')->value())->first();
        // Unified 422 for "no such user" AND "cannot share to self": no directory
        // enumeration — a caller cannot distinguish the two outcomes.
        if (! $recipient instanceof User || $recipient->id === $uid) {
            return response()->json(['error' => 'recipient_not_found'], 422);
        }

        $folderId = $request->integer('file_folder_id');
        $role = $request->string('role')->value();

        $share = DB::transaction(function () use ($folderId, $recipient, $role): FolderShare {
            // Reuse the owner's existing share for that folder if one exists (the
            // find is owner-scoped by the global scope). file_folder_id / owner_id
            // are set explicitly, never mass-assigned (owner_id via AssignsOwner
            // on create); FolderShare/FolderShareMember guard everything but role.
            $share = FolderShare::query()->where('file_folder_id', $folderId)->first();
            if (! $share instanceof FolderShare) {
                $share = new FolderShare;
                $share->file_folder_id = $folderId;
                $share->save();
            }

            $member = $share->members()->where('user_id', $recipient->id)->first();
            if (! $member instanceof FolderShareMember) {
                $member = new FolderShareMember;
                $member->folder_share_id = $share->id;
                $member->user_id = $recipient->id;
            }
            $member->role = $role;
            $member->save();

            return $share;
        });

        return response()->json(['share' => $this->shareView($share->fresh() ?? $share)], 201);
    }

    /** Change a member's role on a share the owner controls. */
    public function updateMember(Request $request, int $share): JsonResponse
    {
        $shareModel = $this->ownedShare($request, $share);
        $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(['viewer', 'editor'])],
        ]);

        $member = $shareModel->members()->where('user_id', $request->integer('user_id'))->first();
        abort_if($member === null, 404);
        $member->role = $request->string('role')->value();
        $member->save();

        return response()->json(['share' => $this->shareView($shareModel->fresh() ?? $shareModel)]);
    }

    /** Revoke a member's access (deletes the grant; other members untouched). */
    public function removeMember(Request $request, int $share): JsonResponse
    {
        $shareModel = $this->ownedShare($request, $share);
        $request->validate(['user_id' => ['required', 'integer']]);

        $shareModel->members()->where('user_id', $request->integer('user_id'))->delete();

        return response()->json(['ok' => true]);
    }

    /** Delete the whole share (cascades the member grants; files are untouched). */
    public function destroy(Request $request, int $share): JsonResponse
    {
        $this->ownedShare($request, $share)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Resolve a share owned by the authenticated caller, or 404. Resolves the id
     * manually (not via route-model binding) so the owner routes behave exactly
     * like the member routes, independent of the SubstituteBindings middleware;
     * the OwnsUserData owner scope + the manage policy both gate it.
     */
    private function ownedShare(Request $request, int $share): FolderShare
    {
        $model = FolderShare::query()->whereKey($share)->first();
        abort_unless($model instanceof FolderShare && $this->requireUser($request)->can('manage', $model), 404);

        return $model;
    }

    /**
     * Owner-visible share representation (folder name + member roster).
     *
     * @return array<string, mixed>
     */
    private function shareView(FolderShare $share): array
    {
        $members = $share->members()->with('user:id,name,email')->get()->map(fn (FolderShareMember $m): array => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user?->name,
            'email' => $m->user?->email,
            'role' => $m->role,
        ])->values();

        return [
            'id' => $share->id,
            'file_folder_id' => $share->file_folder_id,
            'folder_name' => $share->folder?->name,
            'members' => $members,
        ];
    }
}
