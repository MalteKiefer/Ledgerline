<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentDeadline;
use App\Services\Deadlines\DeadlineScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deadlines found in documents (contract ends, notice periods, warranties,
 * expiry dates). Owner-scoped through the model's global scope.
 *
 * A finding is a suggestion until it is confirmed — only a confirmed one is ever
 * reminded about. Dismissing is remembered, so a re-scan cannot resurrect
 * something already judged and rejected.
 */
class DeadlineController extends Controller
{
    /** Upcoming and unjudged findings, soonest first. */
    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json([
            'deadlines' => DocumentDeadline::query()
                ->whereNull('dismissed_at')
                ->orderBy('due_on')
                ->limit(500)
                ->get(),
        ]);
    }

    /** Confirm ("this is a real deadline"), dismiss, or correct one. */
    public function update(Request $request, DocumentDeadline $deadline): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            'confirmed' => ['sometimes', 'boolean'],
            'dismissed' => ['sometimes', 'boolean'],
            'due_on' => ['sometimes', 'date'],
            'label' => ['sometimes', 'nullable', 'string', 'max:300'],
            'kind' => ['sometimes', 'string', Rule::in(['contract_end', 'notice', 'warranty', 'expiry', 'other'])],
        ]);

        $patch = [];
        if ($request->has('confirmed')) {
            $patch['confirmed_at'] = $request->boolean('confirmed') ? now() : null;
        }
        if ($request->has('dismissed')) {
            $patch['dismissed_at'] = $request->boolean('dismissed') ? now() : null;
        }
        foreach (['due_on', 'label', 'kind'] as $field) {
            if ($request->has($field)) {
                $patch[$field] = $request->input($field);
            }
        }
        // A corrected date is a different deadline, so the reminder must fire
        // again for it rather than count as already sent.
        if (array_key_exists('due_on', $patch)) {
            $patch['reminded_at'] = null;
        }
        $deadline->forceFill($patch)->save();

        return response()->json(['deadline' => $deadline->fresh()]);
    }

    /** Re-read this user's documents now instead of waiting for the nightly run. */
    public function scan(Request $request, DeadlineScanner $scanner): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json($scanner->scanUser($uid));
    }
}
