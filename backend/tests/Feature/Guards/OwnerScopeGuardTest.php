<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

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
    ];

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
            "Model(s) with user_id and no OwnsUserData. Add the trait, or — if the model is scoped through a "
            ."parent or is intentionally shared — add it to SCOPED_ELSEWHERE with the reason.");
    }
}
