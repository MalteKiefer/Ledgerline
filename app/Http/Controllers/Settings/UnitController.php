<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UnitRequest;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manage the multilingual unit types used on line items.
 */
class UnitController extends Controller
{
    public function index(): View
    {
        return view('settings.units.index', [
            'units' => Unit::query()->orderBy('code')->get(),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        Unit::create($request->validated());

        return redirect()->route('settings.units.index')->with('status', __('flash.unit_added'));
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return redirect()->route('settings.units.index')->with('status', __('flash.unit_updated'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route('settings.units.index')->with('status', __('flash.unit_deleted'));
    }
}
