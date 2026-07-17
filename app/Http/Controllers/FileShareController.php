<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPublicShares;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-side management of public file/folder share links. Reuses the shared
 * share CRUD; the kind ('file' or 'folder') comes from the request so a single
 * file or a whole folder subtree can be shared. Blobs live under the files disk
 * prefix — the public routes resolve that from the share's kind.
 */
class FileShareController extends Controller
{
    use ManagesPublicShares;

    public function store(Request $request): JsonResponse
    {
        $kind = (string) $request->input('kind');
        abort_unless(in_array($kind, ['file', 'folder'], true), 422);

        return $this->createShare($request, $kind);
    }

    public function update(Request $request, string $token): JsonResponse
    {
        return $this->updateShareRecord($request, $token);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        return $this->destroyShareRecord($request, $token);
    }
}
