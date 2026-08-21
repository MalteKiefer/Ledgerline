<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * A model that carries user_id but no global owner scope is one forgotten
 * where() away from serving another account's rows. That is the most expensive
 * class of bug this application can have, and it is invisible in review — the
 * query looks perfectly ordinary.
 *
 * The list below is the state when this guard was introduced, not an
 * endorsement of each entry: those models scope somewhere else (through a
 * parent relation, explicitly in their controller) or are deliberately shared.
 * The point is that a NEW model cannot join them silently.
 */
class OwnerScopeGuardTest extends TestCase
{
    /**
     * Reviewed exceptions. Before adding one, be able to answer: what stops a
     * query on this model from returning another user's rows?
     *
     * @var list<string>
     */
    private const SCOPED_ELSEWHERE = [
        // Append-only operational logs, read through admin-gated endpoints.
        'AuditLog', 'RequestLog', 'DeviceAccessLog', 'MailLog',
        // Pre-authentication flows: no session exists yet to scope against.
        'DevicePairing', 'InviteLink',
        // Resolved per user explicitly (UserSetting::for($userId)).
        'UserSetting',
        // Mail archive: scoped through the owning account/message in the
        // controllers, which is also where the module gate lives.
        'MailAccount', 'MailMessage', 'MailAttachment', 'MailBlob', 'MailLabel',
        'MailPgpKey', 'MailRule', 'MailSavedSearch', 'MailSyncState',
        // Deliberately cross-user: a share recipient acts on the owner's row.
        // See the sharing entries in the CLAUDE.md security register.
        'FolderShareMember', 'GalleryPhotoComment', 'GalleryPhotoReaction',
        'ContactDuplicateDismissal',
        // Reached only through the owning Server, which is owner-scoped; written
        // solely by the collector job.
        'ServerFact',
    ];

    /**
     * An id arriving in a request body is checked with Rule::exists. Existence
     * alone says the row is real, not that it belongs to the caller — a mail rule
     * once accepted another account's label id that way and would have hung a
     * foreign label on this account's messages.
     *
     * The model-level check above cannot see this: the offending id never becomes
     * a bound model. Every Rule::exists in the backend currently constrains the
     * owner, so this holds the line rather than describing an aspiration.
     */
    public function test_existence_rules_constrain_the_owner(): void
    {
        $offenders = [];
        foreach ((array) glob(app_path('*'), GLOB_ONLYDIR) as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator((string) $dir));
            foreach ($it as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                $offset = 0;
                while (($at = strpos($source, 'Rule::exists(', $offset)) !== false) {
                    $offset = $at + 1;
                    // The rule and its constraints are one fluent chain; a window
                    // covers it whether or not it is wrapped across lines.
                    if (! str_contains(substr($source, $at, 240), '->where(')) {
                        $line = substr_count(substr($source, 0, $at), "\n") + 1;
                        $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.$line;
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            'Rule::exists without a constraint. Existence is not ownership: add a where() on the owner column '
            .'(or the equivalent scope) so a caller cannot name a row that belongs to somebody else.');
    }

    public function test_models_with_a_user_id_use_the_owner_scope(): void
    {
        $offenders = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $name = basename($file, '.php');
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'user_id') || str_contains($source, 'use OwnsUserData;')) {
                continue;
            }
            if (in_array($name, self::SCOPED_ELSEWHERE, true)) {
                continue;
            }
            $offenders[] = $name;
        }

        $this->assertSame([], $offenders,
            'Model(s) with user_id and no OwnsUserData. Add the trait, or — if the model is scoped through a '
            .'parent or is intentionally shared — add it to SCOPED_ELSEWHERE with the reason.');
    }
}
