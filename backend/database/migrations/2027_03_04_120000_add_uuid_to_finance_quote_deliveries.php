<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_quote_deliveries', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable();
        });

        DB::table('finance_quote_deliveries')
            ->select(['id', 'message_id'])
            ->orderBy('id')
            ->eachById(function (object $delivery): void {
                $messageId = is_string($delivery->message_id ?? null) ? $delivery->message_id : '';
                $uuid = preg_match(
                    '/\A<([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})@quotes\.ledgerline>\z/D',
                    $messageId,
                    $matches,
                ) === 1 ? $matches[1] : (string) Str::uuid();

                DB::table('finance_quote_deliveries')
                    ->where('id', $delivery->id)
                    ->update([
                        'uuid' => $uuid,
                        'message_id' => '<'.$uuid.'@quotes.ledgerline>',
                    ]);
            });

        Schema::table('finance_quote_deliveries', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique(['user_id', 'uuid'], 'finance_quote_deliveries_owner_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('finance_quote_deliveries', function (Blueprint $table): void {
            $table->dropUnique('finance_quote_deliveries_owner_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
};
