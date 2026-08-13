<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Calendar CRUD (CalDAV collections), scoped to the authenticated user. Mirrors AddressBookController. */
class CalendarBookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            // A task-list calendar (VTODO) vs an event calendar (VEVENT, default).
            'component' => ['nullable', 'in:VEVENT,VTODO'],
        ]);
        $name = (string) $request->string('name');
        $component = strtoupper((string) $request->string('component')) === Calendar::COMPONENT_TODO
            ? Calendar::COMPONENT_TODO
            : Calendar::COMPONENT_EVENT;
        $calendar = Calendar::create([
            'user_id' => $this->requireUser($request)->id,
            'name' => $name,
            'uri' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'color' => $request->filled('color') ? (string) $request->string('color') : null,
            'component' => $component,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);

        return response()->json(['id' => $calendar->id], 201);
    }

    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorizeCalendar($calendar);
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            'description' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);
        $patch = [];
        foreach (['name', 'color', 'description', 'timezone'] as $field) {
            if ($request->has($field)) {
                $patch[$field] = $request->filled($field) ? (string) $request->string($field) : null;
            }
        }
        if ($patch !== []) {
            $calendar->update($patch);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Calendar $calendar): JsonResponse
    {
        $this->authorizeCalendar($calendar);
        // Keep at least one calendar.
        abort_if(Calendar::where('user_id', $calendar->user_id)->count() <= 1, 422);
        $calendar->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeCalendar(Calendar $calendar): void
    {
        abort_unless($calendar->user_id === auth()->id(), 403);
    }
}
