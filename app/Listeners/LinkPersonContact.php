<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PersonNamed;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\Face;
use App\Models\Person;
use App\Services\Contacts\ContactWriter;
use Illuminate\Support\Facades\Storage;

/**
 * When a gallery person is named, link it to a vCard contact — reusing an
 * existing contact of the same name or creating one in the default address book,
 * and seeding the contact's photo from the person's cover face.
 */
class LinkPersonContact
{
    public function __construct(private readonly ContactWriter $writer) {}

    public function handle(PersonNamed $event): void
    {
        // The naming user owns the contact side (gallery/People are single-tenant
        // and carry no owner, so scope to whoever named the person).
        if ($event->userId === null) {
            return;
        }

        $person = Person::find($event->personId);
        if ($person === null) {
            return;
        }

        // The user's address books only.
        $bookIds = AddressBook::where('user_id', $event->userId)->orderBy('created_at')->pluck('id');
        if ($bookIds->isEmpty()) {
            return; // contacts not enabled for this user yet
        }

        // Already linked to one of this user's contacts → keep its name in step.
        if ($person->contact_id !== null) {
            $contact = Contact::whereIn('address_book_id', $bookIds)->find($person->contact_id);
            if ($contact !== null) {
                $this->writer->update($contact, ['fn' => $event->name] + $this->base($contact), $contact->groups()->pluck('contact_groups.id')->all());

                return;
            }
        }

        // Reuse an existing contact of the same name (in the user's books), else create.
        $contact = Contact::whereIn('address_book_id', $bookIds)->where('fn', $event->name)->first()
            ?? $this->writer->create(AddressBook::find($bookIds->first()), ['fn' => $event->name, 'photo' => $this->coverPhoto($person)]);

        $person->forceFill(['contact_id' => $contact->id])->save();
    }

    /** @return array<string, mixed> */
    private function base(Contact $contact): array
    {
        return ['first_name' => $contact->first_name, 'last_name' => $contact->last_name, 'org' => $contact->org];
    }

    private function coverPhoto(Person $person): ?string
    {
        $face = $person->cover_face_id ? Face::find($person->cover_face_id) : null;
        if ($face === null || $face->thumb_path === null) {
            return null;
        }

        $disk = Storage::disk(config('files.disk'));
        if (! $disk->exists($face->thumb_path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($face->thumb_path));
    }
}
