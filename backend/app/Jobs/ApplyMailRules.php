<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MailMessage;
use App\Models\MailRule;
use App\Support\Mail\RuleEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Runs a user's mail rules over messages that are already archived.
 *
 * Rules only ever ran at ingest, so a rule written today did nothing to the
 * nineteen thousand messages already in the archive — and a new rule is usually
 * written precisely because of what is already there.
 *
 * Deliberately NOT the ingest path:
 *   - `skip` is ignored. It means "do not archive", and the message is archived;
 *     applying it here would have to delete something the user already has,
 *     which is not what "skip" was written to mean.
 *   - `file_receipt` is ignored. Filing needs the attachment BYTES, and doing
 *     that for a whole mailbox would copy thousands of documents into the
 *     receipt inbox on one click. It stays an ingest-time action.
 * What it does apply is what is reversible and cheap: mark read, trash, label.
 *
 * In the worker because it walks the whole archive; a request must not.
 */
class ApplyMailRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param int|null $ruleId one rule, or null for all of the user's enabled rules */
    public function __construct(
        private readonly int $userId,
        private readonly ?int $ruleId = null,
        private readonly ?int $accountId = null,
    ) {}

    public function handle(RuleEvaluator $evaluator): void
    {
        $rules = MailRule::query()->withoutGlobalScopes()
            ->where('user_id', $this->userId)
            ->where('enabled', true)
            ->when($this->ruleId !== null, fn ($q) => $q->whereKey($this->ruleId))
            ->orderBy('priority')
            ->get()
            // Skip rules are dropped, not merely ignored in the result: the
            // evaluator short-circuits on skip and returns an EMPTY action set,
            // so leaving one in would silently disable every other rule for the
            // backfill. A rule that says "do not archive this" has nothing to
            // say about a message that is already archived.
            ->reject(fn (MailRule $rule): bool => ($rule->action_json['skip'] ?? false) === true)
            ->values();

        if ($rules->isEmpty()) {
            return;
        }

        MailMessage::query()->withoutGlobalScopes()
            ->where('user_id', $this->userId)
            ->when($this->accountId !== null, fn ($q) => $q->where('account_id', $this->accountId))
            ->whereNull('trashed_at')
            ->select(['id', 'user_id', 'folder', 'subject', 'from_email', 'from_name', 'to_json', 'has_attachment', 'seen'])
            ->chunkById(500, function ($messages) use ($rules, $evaluator): void {
                foreach ($messages as $message) {
                    $this->applyTo($message, $rules, $evaluator);
                }
            });
    }

    /**
     * @param  Collection<int, MailRule>  $rules
     */
    private function applyTo(MailMessage $message, $rules, RuleEvaluator $evaluator): void
    {
        $recipients = is_array($message->to_json) ? $message->to_json : [];
        $out = $evaluator->evaluate($rules, [
            'from' => (string) ($message->from_email ?? '').' '.(string) ($message->from_name ?? ''),
            'to' => implode(' ', array_map(
                fn ($r): string => is_array($r) ? (string) ($r['email'] ?? '') : '',
                $recipients
            )),
            'subject' => (string) ($message->subject ?? ''),
            'folder' => (string) $message->folder,
            'has_attachment' => (bool) $message->has_attachment,
        ]);

        $patch = [];
        if ($out['mark_read'] && ! $message->seen) {
            $patch['seen'] = true;
            $patch['seen_at'] = now();
        }
        if ($out['trash']) {
            $patch['trashed_at'] = now();
        }
        if ($patch !== []) {
            $message->forceFill($patch)->saveQuietly();
        }
        if ($out['label_ids'] !== []) {
            // syncWithoutDetaching: a rule adds a label, it does not own the set.
            $message->labels()->syncWithoutDetaching($out['label_ids']);
        }
    }
}
