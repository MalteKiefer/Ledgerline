<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TagRequest;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Manage tags (name, colour, add, delete).
 */
class TagController extends Controller
{
    public function index(): View
    {
        $tags = Tag::query()
            ->withCount(['projects', 'files'])
            ->orderBy('name')
            ->get();

        return view('settings.tags.index', ['tags' => $tags]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $slug = Str::slug($request->validated()['name']);

        if (Tag::query()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.'])->withInput();
        }

        Tag::create([
            'name' => $request->validated()['name'],
            'slug' => $slug,
            'color' => $request->validated()['color'] ?? null,
        ]);

        return redirect()->route('settings.tags.index')->with('status', __('flash.tag_added'));
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $slug = Str::slug($request->validated()['name']);

        $duplicate = Tag::query()
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

        return redirect()->route('settings.tags.index')->with('status', __('flash.tag_updated'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('settings.tags.index')->with('status', __('flash.tag_deleted'));
    }
}
