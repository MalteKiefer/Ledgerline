<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mail accounts move out of the encrypted vault into plain rows. Only
        // the login password is kept encrypted at rest (security exception),
        // decryptable server-side to run the IMAP connection + hourly sync.
        Schema::create('mail_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption')->default('ssl'); // ssl | starttls
            $table->boolean('validate_cert')->default(true);
            $table->string('username');
            $table->text('password'); // encrypted cast
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
