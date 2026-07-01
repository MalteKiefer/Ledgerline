<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TagRequest;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manage the active team's tags (name, colour, add, delete).
 *
 * Tags are team-scoped; the global scope on Tag limits every query and the
 * route-bound tag to the user's teams.
 */
class TagController extends Controller
{
    public function index(Request $request): View
    {
        $tags = Tag::query()
            ->where('team_id', $request->user()->currentTeamId())
            ->withCount(['projects', 'files'])
            ->orderBy('name')
            ->get();

        return view('settings.tags.index', ['tags' => $tags]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $teamId = $request->user()->currentTeamId();
        $slug = Str::slug($request->validated()['name']);

        if (Tag::query()->where('team_id', $teamId)->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.'])->withInput();
        }

        Tag::create([
            'team_id' => $teamId,
            'name' => $request->validated()['name'],
            'slug' => $slug,
            'color' => $request->validated()['color'] ?? null,
        ]);

        return redirect()->route('settings.tags.index')->with('status', 'Tag added.');
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $slug = Str::slug($request->validated()['name']);

        $duplicate = Tag::query()
            ->where('team_id', $tag->team_id)
            ->where('slug', $slug)
            ->whereKeyNot($tag->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['name' => 'A tag with this name already exists.']);
        }

        $tag->update([
            'name' => $request->validated()['name'],
            'slug' => $slug,
            'color' => $request->validated()['color'] ?? null,
        ]);

        return redirect()->route('settings.tags.index')->with('status', 'Tag updated.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('settings.tags.index')->with('status', 'Tag deleted.');
    }
}
