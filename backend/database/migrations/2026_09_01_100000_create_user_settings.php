<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user personal preferences (the calendar display + generated-calendar
 * settings that used to live global on app_settings). Infra settings stay on
 * app_settings. The first user inherits the previous global values so nothing
 * changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary();
            $table->string('calendar_week_start', 10)->default('monday');
            $table->boolean('calendar_week_numbers')->default(false);
            $table->unsignedInteger('calendar_default_event_minutes')->default(60);
            $table->string('calendar_timezone', 64)->nullable();
            $table->boolean('calendar_birthdays_enabled')->default(false);
            $table->boolean('calendar_anniversaries_enabled')->default(false);
            $table->json('calendar_holiday_countries')->nullable();
            $table->timestamps();
        });

        // Seed the first user from the previous global calendar settings.
        $firstUserId = User::query()->orderBy('id')->value('id');
        $app = DB::table('app_settings')->first();
        if ($firstUserId !== null && $app !== null) {
            DB::table('user_settings')->insert([
                'user_id' => $firstUserId,
                'calendar_week_start' => $app->calendar_week_start ?? 'monday',
                'calendar_week_numbers' => $app->calendar_week_numbers ?? false,
                'calendar_default_event_minutes' => $app->calendar_default_event_minutes ?? 60,
                'calendar_timezone' => $app->calendar_timezone ?? null,
                'calendar_birthdays_enabled' => $app->calendar_birthdays_enabled ?? false,
                'calendar_anniversaries_enabled' => $app->calendar_anniversaries_enabled ?? false,
                'calendar_holiday_countries' => $app->calendar_holiday_countries ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
