<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, non-secret calendar display preferences (week numbers, week start,
 * default view, default working hours). Presentation only — the calendar data
 * itself stays zero-knowledge in the sealed store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('cal_week_numbers')->default(false);
            $table->string('cal_week_start', 3)->default('mon');   // mon | sun
            $table->string('cal_default_view', 8)->default('month'); // month | week | day
            $table->unsignedTinyInteger('cal_day_start')->default(8);
            $table->unsignedTinyInteger('cal_day_end')->default(17);
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['cal_week_numbers', 'cal_week_start', 'cal_default_view', 'cal_day_start', 'cal_day_end']);
        });
    }
};
