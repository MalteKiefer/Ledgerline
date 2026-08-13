<?php

declare(strict_types=1);

namespace App\Services\Backup;

use RuntimeException;

/**
 * Thrown at a checkpoint when the operator has requested the running backup to
 * stop. Caught by BackupManager, which records the run as "cancelled".
 */
final class BackupCancelled extends RuntimeException {}
