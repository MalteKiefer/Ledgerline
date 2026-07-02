<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Manage virtual folders for organising files.
 */
class FolderController extends Controller
{
    public function store(StoreFolderRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $folder = Folder::create([
            'name' => filled($data['enc_name'] ?? null) ? '' : $data['name'],
            'enc_name' => $data['enc_name'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        // Encrypted folder-tree creation during an upload drives this over AJAX
        // and needs the new id to nest children and place files.
        if ($request->expectsJson()) {
            return response()->json(['id' => $folder->id, 'parent_id' => $folder->parent_id], 201);
        }

        return redirect()
            ->route('files.index', ['folder' => $folder->parent_id])
            ->with('status', __('flash.folder_created'));
    }

    public function update(StoreFolderRequest $request, Folder $folder): RedirectResponse
    {
        // Only the name is editable here; the parent is fixed at creation.
        $data = $request->validated();
        $folder->update(filled($data['enc_name'] ?? null)
            ? ['name' => '', 'enc_name' => $data['enc_name']]
            : ['name' => $data['name'], 'enc_name' => null]);

        return redirect()
            ->route('files.index', ['folder' => $folder->id])
            ->with('status', __('flash.folder_renamed'));
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $parentId = $folder->parent_id;

        // Move contents up to the parent so nothing is lost, then remove the folder.
        DB::transaction(function () use ($folder, $parentId): void {
            Folder::where('parent_id', $folder->id)->update(['parent_id' => $parentId]);
            File::where('folder_id', $folder->id)->update(['folder_id' => $parentId]);
            $folder->delete();
        });

        return redirect()
            ->route('files.index', ['folder' => $parentId])
            ->with('status', __('flash.folder_deleted'));
    }
}
