<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a face-recognition person to an address-book contact (opt-in). Naming a
 * person from the address book sets contact_id; the contact page can then show
 * that person's photos. Nullable — a person may keep a free-text name only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_people', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->nullable()->after('name')
                ->constrained('contacts')->nullOnDelete();
            $table->index(['user_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_people', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
