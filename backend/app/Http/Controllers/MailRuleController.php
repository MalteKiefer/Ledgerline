<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ApplyMailRules;
use App\Models\MailRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner-scoped CRUD for sieve-lite ingest rules, evaluated at ingest
 * (MaildirIngestor / RuleEvaluator). A foreign / unknown id is a 404.
 */
class MailRuleController extends Controller
{
    /**
     * Run a rule (or all of them) over mail that is already archived.
     *
     * Rules only ran at ingest, so a rule written today did nothing about what
     * is already there — which is usually why it was written. Queued because it
     * walks the whole archive; a request must not.
     */
    public function apply(Request $request, ?MailRule $rule = null): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($rule !== null) {
            $this->authorizeOwner($request, $rule);
        }

        ApplyMailRules::dispatch(
            $user->id,
            $rule?->id,
            $request->filled('account_id') ? $request->integer('account_id') : null,
        );

        return response()->json(['dispatched' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $rules = MailRule::query()
            ->ownedBy($this->requireUser($request)->id)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return response()->json(['rules' => $rules->map(fn (MailRule $r): array => $this->present($r))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $rule = new MailRule($this->validated($request)); // AssignsOwner stamps user_id
        $rule->save();

        return response()->json(['rule' => $this->present($rule)], 201);
    }

    public function update(Request $request, MailRule $rule): JsonResponse
    {
        $this->authorizeOwner($request, $rule);
        $rule->update($this->validated($request));

        return response()->json(['rule' => $this->present($rule->fresh() ?? $rule)]);
    }

    public function destroy(Request $request, MailRule $rule): JsonResponse
    {
        $this->authorizeOwner($request, $rule);
        $rule->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'enabled' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'match' => ['required', 'array'],
            'match.from' => ['nullable', 'string', 'max:255'],
            'match.to' => ['nullable', 'string', 'max:255'],
            'match.subject' => ['nullable', 'string', 'max:255'],
            'match.folder' => ['nullable', 'string', 'max:255'],
            'match.has_attachment' => ['nullable', 'boolean'],
            'action' => ['required', 'array'],
            // A rule may only attach a label the caller owns; otherwise it would
            // hang another account's label on this account's messages.
            'action.add_label' => ['nullable', 'integer', Rule::exists('mail_labels', 'id')->where('user_id', $this->requireUser($request)->id)],
            'action.mark_read' => ['nullable', 'boolean'],
            'action.trash' => ['nullable', 'boolean'],
            'action.skip' => ['nullable', 'boolean'],
            // File the message's attachments in the finance receipt inbox.
            'action.file_receipt' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $request->string('name')->value(),
            'enabled' => $request->boolean('enabled', true),
            'priority' => $request->integer('priority', 0),
            'match_json' => (array) $request->input('match', []),
            'action_json' => (array) $request->input('action', []),
        ];
    }

    /** @return array<string, mixed> */
    private function present(MailRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'enabled' => $rule->enabled,
            'priority' => $rule->priority,
            'match' => $rule->match_json,
            'action' => $rule->action_json,
        ];
    }

    private function authorizeOwner(Request $request, MailRule $rule): void
    {
        abort_if((int) $rule->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
