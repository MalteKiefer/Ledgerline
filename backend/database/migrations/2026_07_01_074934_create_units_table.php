<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the units table (multilingual unit types for line items) and seed a
 * default set. Each unit carries its UN/ECE code so e-invoices (ZUGFeRD) use the
 * correct quantity unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_de');
            $table->string('name_en');
            $table->string('zugferd_code', 8)->default('C62');
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['h', 'Stunde', 'Hour', 'HUR'],
            ['day', 'Tag', 'Day', 'DAY'],
            ['pcs', 'Stück', 'Piece', 'C62'],
            ['flat', 'Pauschale', 'Flat rate', 'C62'],
            ['km', 'Kilometer', 'Kilometre', 'KMT'],
            ['month', 'Monat', 'Month', 'MON'],
            ['lic', 'Lizenz', 'License', 'C62'],
        ];

        DB::table('units')->insert(array_map(fn (array $u): array => [
            'code' => $u[0],
            'name_de' => $u[1],
            'name_en' => $u[2],
            'zugferd_code' => $u[3],
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults));
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
