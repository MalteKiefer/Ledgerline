<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AppSettings;
use App\Models\Contact;
use App\Services\Calendar\ContactDerivedCalendars;

/**
 * Rebuilds the derived birthdays/anniversaries calendars when a contact changes,
 * but only while at least one of those calendars is enabled (cheap no-op
 * otherwise).
 */
class ContactObserver
{
    public function __construct(private readonly ContactDerivedCalendars $derived) {}

    public function saved(Contact $contact): void
    {
        $this->resync();
    }

    public function deleted(Contact $contact): void
    {
        $this->resync();
    }

    private function resync(): void
    {
        $settings = AppSettings::current();
        if ($settings->calendar_birthdays_enabled || $settings->calendar_anniversaries_enabled) {
            $this->derived->sync();
        }
    }
}
