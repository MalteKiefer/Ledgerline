<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\User;

/**
 * A module's contribution to the two account-wide, per-user data operations:
 * GDPR export ("give me all my data") and account erasure ("delete everything").
 * One implementation per module; registered in config/user_data.php.
 */
interface UserDataContributor
{
    /** Machine key for the export section, e.g. "notes". */
    public function key(): string;

    /** A JSON-serializable snapshot of the user's data in this module. */
    public function export(User $user): array;

    /**
     * Permanently delete every piece of the user's data in this module,
     * including any stored file blobs. Called inside a DB transaction during
     * account erasure; must be idempotent and owner-scoped.
     */
    public function purge(User $user): void;
}
