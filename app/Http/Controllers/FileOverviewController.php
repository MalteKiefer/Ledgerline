<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Models\File;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lists every file across the current user's team(s), with an optional type
 * filter. Reachable from the main menu next to Projects.
 */
class FileOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', File::class);

        $type = FileType::tryFrom((string) $request->query('type'));

        $files = File::query()
            ->with(['attachable', 'tags'])
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('files.index', [
            'files' => $files,
            'types' => FileType::options(),
            'activeType' => $type?->value,
        ]);
    }
}
