<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailAccount>
 */
class MailAccountFactory extends Factory
{
    protected $model = MailAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'host' => 'imap.'.fake()->domainName(),
            'port' => 993,
            'username' => fake()->safeEmail(),
            'password' => fake()->password(),
            'encryption' => 'ssl',
            'folders' => null,
            'backfill_since' => null,
            'delete_after_import' => false,
            'skip_spam' => true,
            'enabled' => true,
            'sync_interval_minutes' => null,
            'status' => MailAccount::STATUSES[0],
            'last_error' => null,
            'last_synced_at' => null,
        ];
    }
}
