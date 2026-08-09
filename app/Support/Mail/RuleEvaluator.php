<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailRule;
use Illuminate\Support\Collection;

/**
 * Evaluates a user's ingest rules against one parsed message and aggregates
 * their actions. A rule matches when EVERY present condition matches (AND):
 *   from/to/subject  — case-insensitive substring
 *   folder           — exact (case-insensitive)
 *   has_attachment   — boolean equality
 * Actions (aggregated across all matching rules, in priority order):
 *   skip       — do not archive at all (short-circuits)
 *   mark_read  — store as \Seen
 *   trash      — store soft-hidden (trashed_at)
 *   add_label  — attach the given label id after the row is written
 */
final class RuleEvaluator
{
    /**
     * @param  Collection<int, MailRule>  $rules
     * @param  array{from:string, to:string, subject:string, folder:string, has_attachment:bool}  $ctx
     * @return array{skip:bool, mark_read:bool, trash:bool, label_ids:list<int>}
     */
    public function evaluate(Collection $rules, array $ctx): array
    {
        $out = ['skip' => false, 'mark_read' => false, 'trash' => false, 'label_ids' => []];

        foreach ($rules as $rule) {
            if (! $this->matches($rule->match_json, $ctx)) {
                continue;
            }

            $action = $rule->action_json;
            if (($action['skip'] ?? false) === true) {
                return ['skip' => true, 'mark_read' => false, 'trash' => false, 'label_ids' => []];
            }
            if (($action['mark_read'] ?? false) === true) {
                $out['mark_read'] = true;
            }
            if (($action['trash'] ?? false) === true) {
                $out['trash'] = true;
            }
            $label = $action['add_label'] ?? null;
            if (is_numeric($label)) {
                $out['label_ids'][] = (int) $label;
            }
        }

        $out['label_ids'] = array_values(array_unique($out['label_ids']));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array{from:string, to:string, subject:string, folder:string, has_attachment:bool}  $ctx
     */
    private function matches(array $match, array $ctx): bool
    {
        foreach (['from', 'to', 'subject'] as $field) {
            $needle = $match[$field] ?? null;
            if (is_string($needle) && $needle !== '' && ! str_contains(mb_strtolower($ctx[$field]), mb_strtolower($needle))) {
                return false;
            }
        }

        $folder = $match['folder'] ?? null;
        if (is_string($folder) && $folder !== '' && mb_strtolower($ctx['folder']) !== mb_strtolower($folder)) {
            return false;
        }

        if (array_key_exists('has_attachment', $match) && is_bool($match['has_attachment'])
            && $match['has_attachment'] !== $ctx['has_attachment']) {
            return false;
        }

        // A rule with no conditions never matches (avoids a catch-all that would
        // silently skip/trash every message).
        return $this->hasAnyCondition($match);
    }

    /** @param array<string, mixed> $match */
    private function hasAnyCondition(array $match): bool
    {
        foreach (['from', 'to', 'subject', 'folder'] as $f) {
            if (is_string($match[$f] ?? null) && $match[$f] !== '') {
                return true;
            }
        }

        return array_key_exists('has_attachment', $match) && is_bool($match['has_attachment']);
    }
}
