<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * The outcome of one MbsyncRunner::run() call. Immutable value object; see
 * MbsyncOutcome for what each case means and which side effects it implies.
 */
final readonly class MbsyncResult
{
    /** True only for MbsyncOutcome::Success; convenience over `outcome`. */
    public bool $ok;

    public function __construct(
        public MbsyncOutcome $outcome,
        public ?string $message = null,
    ) {
        $this->ok = $outcome === MbsyncOutcome::Success;
    }

    public static function success(): self
    {
        return new self(MbsyncOutcome::Success);
    }

    public static function hostRejected(string $message): self
    {
        return new self(MbsyncOutcome::HostRejected, $message);
    }

    public static function unavailable(): self
    {
        return new self(
            MbsyncOutcome::Unavailable,
            'The mbsync binary is not installed on this host.',
        );
    }

    public static function failed(string $message): self
    {
        return new self(MbsyncOutcome::Failed, $message);
    }
}
