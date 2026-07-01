<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lists every file across the current user's team(s) and offers an upload form
 * where the target customer or project is chosen. Reachable from the main menu.
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
            'targets' => $this->targetOptions(),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * Build the "customer:<id>" / "project:<id>" options for the upload target,
     * scoped to the user's team by the global scope on both models.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function targetOptions(): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Customer $c): array => ['value' => 'customer:'.$c->id, 'label' => 'Customer: '.$c->name]);

        $projects = Project::query()->with('customer')->orderBy('name')->get()
            ->map(fn (Project $p): array => ['value' => 'project:'.$p->id, 'label' => 'Project: '.$p->name.' ('.$p->customer->name.')']);

        return $customers->concat($projects)->values()->all();
    }
}
