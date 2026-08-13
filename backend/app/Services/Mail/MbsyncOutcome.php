<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * The terminal outcome of one MbsyncRunner::run() call.
 */
enum MbsyncOutcome: string
{
    /** mbsync exited successfully; the account's status/last_error were cleared. */
    case Success = 'success';

    /** The configured host failed the outbound-egress guard — no connection
     *  was ever attempted. The account was marked status=error. */
    case HostRejected = 'host_rejected';

    /** The `mbsync` binary is not installed on this host (dev/CI box without
     *  the deploy image's mail toolchain). Not an account error — status is
     *  left untouched. */
    case Unavailable = 'unavailable';

    /** mbsync ran but exited non-zero (auth failure, network error, timeout,
     *  ...). The account was marked status=error with a redacted message. */
    case Failed = 'failed';
}
