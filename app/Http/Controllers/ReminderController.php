<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Create / update / delete server-side reminders for to-do due dates. The to-do
 * stays zero-knowledge; the client sends only the due time, chosen channels and
 * the title/link needed to fire a notification (stored encrypted).
 */
class ReminderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $reminder = Reminder::create($data);

        return response()->json(['id' => $reminder->id]);
    }

    public function update(Request $request, Reminder $reminder): JsonResponse
    {
        $data = $this->validated($request);
        // Rescheduling re-arms the reminder so it fires again at the new time.
        $data['fired_at'] = null;
        $reminder->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array{due_at:Carbon, channels:list<string>, title:string, url:?string} */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'due_at' => ['required', 'date'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(Reminder::CHANNELS)],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        return [
            // Interpret the client's wall-clock due time in the app timezone,
            // matching how the backup cron schedules are evaluated.
            'due_at' => Carbon::parse($v['due_at'], config('app.timezone')),
            'channels' => array_values(array_unique($v['channels'])),
            'title' => $v['title'],
            'url' => $v['url'] ?? null,
        ];
    }
}
