<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bell menu's local notifications: list recent, report the unread count,
 * and mark read. Scoped to the signed-in user.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = AppNotification::where('user_id', $userId)
            ->latest()
            ->limit(30)
            ->get(['id', 'level', 'category', 'title', 'body', 'read_at', 'created_at']);

        return response()->json([
            'unread' => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
            'items' => $items->map(fn (AppNotification $n): array => [
                'id' => $n->id,
                'level' => $n->level,
                'category' => $n->category,
                'title' => $n->title,
                'body' => $n->body,
                'read' => $n->read_at !== null,
                'at' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
