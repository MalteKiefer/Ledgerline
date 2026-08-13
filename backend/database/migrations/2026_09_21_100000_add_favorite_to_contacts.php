<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Denormalised favorite flag (mirrors the vCard's X-LL-FAVORITE property) so
// the contacts list can filter favorites without parsing every card.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('favorite')->default(false)->after('has_photo');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('favorite');
        });
    }
};
