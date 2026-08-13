<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarShare;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-side calendar sharing: grant a registered user viewer/editor access to
 * one of your calendars. Recipients are resolved only by exact email (unified
 * 422, no directory enumeration); rows are owner-scoped.
 */
class CalendarShareController extends Controller
{
    /** Shares the current user has granted, grouped nowhere — flat list. */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = CalendarShare::query()->where('owner_id', $uid)->with(['recipient:id,email', 'calendar:id,name'])->latest('id')->get()
            ->map(fn (CalendarShare $s): array => [
                'id' => $s->id,
                'calendar_id' => $s->calendar_id,
                'calendar' => $s->calendar?->name,
                'recipient' => $s->recipient?->email,
                'role' => $s->role,
            ])->values();

        return response()->json(['shares' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'calendar_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'role' => ['nullable', 'in:viewer,editor'],
        ]);
        $calendar = Calendar::query()->where('user_id', $user->id)->findOrFail($request->string('calendar_id')->value());

        $recipient = User::query()->where('email', $request->string('email')->value())->first();
        // Unified 422 for "no such user" AND self-share (no enumeration).
        if (! $recipient instanceof User || $recipient->id === $user->id) {
            return response()->json(['message' => 'recipient_invalid'], 422);
        }
        $role = $request->string('role')->value() === 'editor' ? 'editor' : 'viewer';

        $share = CalendarShare::query()->where('calendar_id', $calendar->id)->where('recipient_id', $recipient->id)->first();
        if ($share instanceof CalendarShare) {
            $share->forceFill(['role' => $role])->save();

            return response()->json(['ok' => true, 'id' => $share->id]);
        }
        $share = new CalendarShare;
        $share->forceFill([
            'owner_id' => $user->id,
            'calendar_id' => $calendar->id,
            'recipient_id' => $recipient->id,
            'role' => $role,
        ])->save();

        return response()->json(['ok' => true, 'id' => $share->id], 201);
    }

    public function destroy(Request $request, int $share): JsonResponse
    {
        $user = $this->requireUser($request);
        CalendarShare::query()->where('owner_id', $user->id)->findOrFail($share)->delete();

        return response()->json(['ok' => true]);
    }
}
