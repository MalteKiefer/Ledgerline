<?php

declare(strict_types=1);

use App\Services\Contacts\VCardService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the vCard BDAY to a year-agnostic "MM-DD" so contacts:birthday-remind
 * can match today's birthdays with a cheap column filter instead of parsing every
 * vCard. Backfilled from the stored vCard; kept in sync by VCardService::denormalize().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('bday', 5)->nullable()->after('favorite'); // "MM-DD"
            $table->index('bday');
        });

        $service = app(VCardService::class);
        DB::table('contacts')->select('id', 'vcard')->orderBy('id')->chunk(200, function ($rows) use ($service): void {
            foreach ($rows as $row) {
                $bday = $service->denormalize((string) $row->vcard)['bday'] ?? null;
                if ($bday !== null) {
                    DB::table('contacts')->where('id', $row->id)->update(['bday' => $bday]);
                }
            }
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
