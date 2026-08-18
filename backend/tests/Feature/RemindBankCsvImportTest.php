<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushJob;
use App\Models\AppNotification;
use App\Models\BankTransaction;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemindBankCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reminds_once_every_seven_days_when_no_current_csv_was_imported(): void
    {
        Queue::fake();
        $user = $this->signIn();

        $this->artisan('finance:remind-bank-csv')->assertOk();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'finance_bank_csv']);
        Queue::assertPushed(SendPushJob::class);

        $this->artisan('finance:remind-bank-csv')->assertOk();
        $this->assertSame(1, AppNotification::where('category', 'finance_bank_csv')->count());
    }

    public function test_a_recent_bank_import_suppresses_the_reminder_until_it_is_stale(): void
    {
        $user = $this->signIn();
        $method = PaymentMethod::create(['user_id' => $user->id, 'name' => 'Business account', 'type' => 'bank']);
        BankTransaction::create(['user_id' => $user->id, 'payment_method_id' => $method->id, 'date' => Carbon::today(), 'amount' => 10]);

        $this->artisan('finance:remind-bank-csv')->assertOk();
        $this->assertSame(0, AppNotification::where('category', 'finance_bank_csv')->count());

        BankTransaction::query()->update(['created_at' => Carbon::now()->subDays(8)]);
        $this->artisan('finance:remind-bank-csv')->assertOk();
        $this->assertSame(1, AppNotification::where('category', 'finance_bank_csv')->count());
    }
}
