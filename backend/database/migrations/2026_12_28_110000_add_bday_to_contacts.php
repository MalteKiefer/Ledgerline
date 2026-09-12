<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the vCard BDAY to a year-agnostic "MM-DD" so contacts:birthday-remind
 * can match today's birthdays with a cheap column filter instead of parsing every
 * vCard.
 *
 * The original backfill (parsing every stored vCard via the now-removed
 * VCardService) is gone: the Contacts module and the contacts table it
 * populated are being dropped later in this same migration history, so a fresh
 * install never has rows to backfill anyway, and the removed service no longer
 * exists to run it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('bday', 5)->nullable()->after('favorite'); // "MM-DD"
            $table->index('bday');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex(['bday']);
            $table->dropColumn('bday');
        });
    }
};
