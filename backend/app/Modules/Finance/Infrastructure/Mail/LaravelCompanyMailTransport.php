<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

final class LaravelCompanyMailTransport implements CompanyMailTransport
{
    public function send(string $mailerName, string $recipient, Mailable $mail): void
    {
        Mail::mailer($mailerName)->to($recipient)->send($mail);
    }
}
