<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailLabel;
use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Owner-scoped CRUD for colored mail labels + a bulk apply/remove across
 * messages. Labels are the one mutable piece of user metadata on the otherwise
 * immutable archive. A foreign / unknown id is a 404.
 */
class MailLabelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $labels = MailLabel::query()
            ->ownedBy($this->requireUser($request)->id)
            ->withCount('messages')
            ->orderBy('name')
            ->get();

        return response()->json(['labels' => $labels->map(fn (MailLabel $l): array => $this->present($l))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $label = new MailLabel($data);   // AssignsOwner stamps user_id
        $label->save();

        return response()->json(['label' => $this->present($label)], 201);
    }

    public function update(Request $request, MailLabel $label): JsonResponse
    {
        $this->authorizeOwner($request, $label);
        $label->update($this->validated($request));

        return response()->json(['label' => $this->present($label->fresh() ?? $label)]);
    }

    public function destroy(Request $request, MailLabel $label): JsonResponse
    {
        $this->authorizeOwner($request, $label);
        $label->delete(); // pivot rows cascade

        return response()->json([], 204);
    }

    /** Bulk attach/detach labels across a set of the caller's messages. */
    public function apply(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['string'],
            'add' => ['array'],
            'add.*' => ['integer'],
            'remove' => ['array'],
            'remove.*' => ['integer'],
        ]);

        // Only the caller's messages + labels.
        $messageIds = MailMessage::query()->ownedBy($uid)
            ->whereIn('id', (array) $request->input('ids'))->pluck('id')->all();
        $add = $this->ownLabelIds($uid, (array) $request->input('add', []));
        $remove = $this->ownLabelIds($uid, (array) $request->input('remove', []));

        DB::transaction(function () use ($messageIds, $add, $remove): void {
            foreach (MailMessage::query()->whereIn('id', $messageIds)->get() as $message) {
                if ($add !== []) {
                    $message->labels()->syncWithoutDetaching($add);
                }
                if ($remove !== []) {
                    $message->labels()->detach($remove);
                }
            }
        });

        return response()->json(['updated' => count($messageIds)]);
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int>
     */
    private function ownLabelIds(int $uid, array $ids): array
    {
        $wanted = array_values(array_filter(array_map(
            static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0,
            $ids,
        ), static fn (int $v): bool => $v > 0));
        if ($wanted === []) {
            return [];
        }

        $found = MailLabel::query()->ownedBy($uid)->whereIn('id', $wanted)->pluck('id')->all();

        return array_values(array_map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0, $found));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:16', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
        ]);

        $data = ['name' => $request->string('name')->value()];
        if ($request->filled('color')) {
            $data['color'] = $request->string('color')->value();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(MailLabel $label): array
    {
        return [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
            'message_count' => $label->getAttribute('messages_count') ?? 0,
        ];
    }

    private function authorizeOwner(Request $request, MailLabel $label): void
    {
        abort_if((int) $label->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
