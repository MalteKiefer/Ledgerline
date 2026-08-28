<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use Illuminate\Mail\Mailable;

interface CompanyMailTransport
{
    public function send(string $mailerName, string $recipient, Mailable $mail): void;
}
