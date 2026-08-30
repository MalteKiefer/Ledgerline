<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class LaravelCompanyMailTransport implements CompanyMailTransport
{
    public function send(string $mailerName, string $recipient, Mailable $mail): CompanyMailTransportResult
    {
        try {
            Mail::mailer($mailerName)->to($recipient)->send($mail);
        } catch (CompanyMailTransportFailure $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UncertainMailTransportFailure(
                'The SMTP transport outcome is uncertain.',
                previous: $exception,
            );
        }

        return CompanyMailTransportResult::accepted();
    }
}
