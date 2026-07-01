<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Manage virtual folders for organising files.
 */
class FolderController extends Controller
{
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $folder = Folder::create($request->validated());

        return redirect()
            ->route('files.index', ['folder' => $folder->parent_id])
            ->with('status', 'Folder created.');
    }

    public function update(StoreFolderRequest $request, Folder $folder): RedirectResponse
    {
        // Only the name is editable here; the parent is fixed at creation.
        $folder->update(['name' => $request->validated()['name']]);

        return redirect()
            ->route('files.index', ['folder' => $folder->id])
            ->with('status', 'Folder renamed.');
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
            ->with('status', 'Folder deleted; its contents moved up one level.');
    }
}
