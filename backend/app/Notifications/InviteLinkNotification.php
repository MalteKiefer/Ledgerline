<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Emails an invite / password-reset link (the same link the admin can also copy).
 * Sent over the DB-configured SMTP bridge; only used when mail is enabled.
 */
class InviteLinkNotification extends Notification
{
    public function __construct(private readonly string $url, private readonly ?Carbon $expiresAt) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('auth_ui.invite_mail_subject'))
            ->greeting(__('auth_ui.invite_mail_greeting'))
            ->line(__('auth_ui.invite_mail_line'))
            ->action(__('auth_ui.invite_mail_action'), $this->url);

        if ($this->expiresAt !== null) {
            $mail->line(__('auth_ui.invite_mail_expires', ['time' => $this->expiresAt->toDayDateTimeString()]));
        }

        return $mail;
    }
}
