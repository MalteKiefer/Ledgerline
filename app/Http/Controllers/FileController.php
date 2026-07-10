<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileBlob;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Zero-knowledge file store. The whole directory tree — folder/file names, the
 * hierarchy, tags, notes, favourites, trash flags and version history — lives
 * inside the user's sealed manifest (the opaque store, see StoreController); the
 * server never sees any of it. This controller only handles the OPAQUE CONTENT
 * BLOBS at "files/{blob}" plus the ownership ledger (file_blobs) for quota +
 * access control — all of which lives in the shared BlobStoreController. It
 * cannot read a blob's name, contents, or which manifest row references it.
 */
class FileController extends BlobStoreController
{
    protected function blobModel(): string
    {
        return FileBlob::class;
    }

    protected function module(): string
    {
        return 'files';
    }

    /** Files allow larger whole-uploads than the gallery. */
    protected function maxUploadMb(): int
    {
        return (int) config('files.max_upload_mb', 2048);
    }

    /** The file browser shell. All data flows through the opaque store client-side;
     *  only the per-user version cap + initial usage are handed to the view. */
    public function index(Request $request): View
    {
        $uid = (int) $request->user()->id;

        return view('files.index', [
            'maxVersions' => min(10, max(1, (int) UserSetting::for($uid)->file_max_versions)),
            'usage' => ['used' => $this->usedBytes($uid), 'quota' => $this->quotaBytes()],
        ]);
    }
}
