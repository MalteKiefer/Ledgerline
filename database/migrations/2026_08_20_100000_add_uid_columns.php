<?php

declare(strict_types=1);

use App\Models\CalendarObject;
use App\Models\Contact;
use App\Services\Calendar\ICalService;
use App\Services\Contacts\VCardService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised UID columns for exact, indexed dedup on import — replacing the
 * fragile `... LIKE '%UID:x%'` substring match (which mis-matched 'foo' against
 * 'foobar' and let a crafted UID overwrite the wrong object).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('uid')->nullable()->index()->after('etag');
        });
        Schema::table('calendar_objects', function (Blueprint $table): void {
            $table->string('uid')->nullable()->index()->after('etag');
        });

        // Backfill existing rows from the stored payload.
        foreach (Contact::query()->cursor() as $contact) {
            $uid = app(VCardService::class)->parse($contact->vcard)['uid'] ?? null;
            if ($uid !== null) {
                $contact->forceFill(['uid' => $uid])->saveQuietly();
            }
        }
        foreach (CalendarObject::query()->cursor() as $object) {
            $uid = app(ICalService::class)->uid($object->ics);
            if ($uid !== null) {
                $object->forceFill(['uid' => $uid])->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('uid'));
        Schema::table('calendar_objects', fn (Blueprint $table) => $table->dropColumn('uid'));
    }
};
