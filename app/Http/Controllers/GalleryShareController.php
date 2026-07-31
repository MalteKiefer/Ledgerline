<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPublicShares;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-side management of public gallery-album share links. All the CRUD +
 * ownership scoping lives in ManagesPublicShares; this only pins the kind.
 */
class GalleryShareController extends Controller
{
    use ManagesPublicShares;

    public function store(Request $request): JsonResponse
    {
        return $this->createShare($request, 'gallery_album');
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
