<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\Contacts\VCardService;
use Illuminate\Console\Command;

/**
 * Re-derives the denormalised contact columns (fn/org/emails/phones/bday …)
 * from each stored vCard, without touching the vCard, etag or DAV sync token.
 * One-off repair for rows imported before the parser fixes (Apple ORG trailing
 * ";", duplicate phone entries, packed addresses) so the list/search columns
 * match what the detail view already parses live.
 */
class RedenormalizeContacts extends Command
{
    protected $signature = 'contacts:redenormalize';

    protected $description = 'Rebuild denormalised contact columns from stored vCards';

    public function handle(VCardService $vcards): int
    {
        $changed = 0;

        Contact::query()->chunkById(200, function ($contacts) use ($vcards, &$changed): void {
            foreach ($contacts as $contact) {
                $fresh = $vcards->denormalize($contact->vcard);
                // Compare only the fields we own here; skip a pointless write.
                $diff = false;
                foreach ($fresh as $key => $value) {
                    if ($contact->{$key} != $value) {
                        $diff = true;
                        break;
                    }
                }
                if ($diff) {
                    $contact->forceFill($fresh)->saveQuietly();
                    $changed++;
                }
            }
        });

        $this->info("Redenormalised {$changed} contact(s).");

        return self::SUCCESS;
    }
}
