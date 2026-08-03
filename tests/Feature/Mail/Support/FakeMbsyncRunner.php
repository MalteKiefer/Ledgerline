<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Support;

use App\Models\MailAccount;
use App\Services\Mail\MbsyncOutcome;
use App\Services\Mail\MbsyncResult;
use App\Services\Mail\MbsyncRunner;
use Closure;

/**
 * Test double for MbsyncRunner: never touches the network or shells mbsync.
 * Instead it returns a canned MbsyncResult and, via an optional $onRun hook,
 * drops fixture Maildir files into the account's REAL scratch path (the same
 * storage_path the runner uses) so the producer's folder enumeration + the
 * real MaildirIngestor run against them exactly as they would in production.
 *
 * Bound over the concrete MbsyncRunner in the container so SyncMailAccount
 * resolves this instead — which is why MbsyncRunner is no longer `final`.
 */
class FakeMbsyncRunner extends MbsyncRunner
{
    public int $runCount = 0;

    /** @var Closure(MailAccount):void|null */
    public ?Closure $onRun = null;

    public function __construct(public MbsyncResult $result = new MbsyncResult(MbsyncOutcome::Success))
    {
        // Deliberately does not call parent::__construct — no MbsyncConfig needed.
    }

    public function run(MailAccount $account): MbsyncResult
    {
        $this->runCount++;

        if ($this->onRun !== null) {
            ($this->onRun)($account);
        }

        return $this->result;
    }
}
