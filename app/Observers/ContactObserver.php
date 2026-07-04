<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Contact;
use App\Models\UserSetting;
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
        $this->resync($contact);
    }

    public function deleted(Contact $contact): void
    {
        $this->resync($contact);
    }

    private function resync(Contact $contact): void
    {
        // A contact belongs to exactly one address book/user — rebuild only that
        // user's derived calendars, and only when they've enabled one.
        $userId = $contact->addressBook?->user_id;
        if ($userId === null) {
            return;
        }
        $settings = UserSetting::for((int) $userId);
        if (! $settings->calendar_birthdays_enabled && ! $settings->calendar_anniversaries_enabled) {
            return;
        }
        $this->derived->sync((int) $userId);
    }
}
