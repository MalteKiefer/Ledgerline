<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\Invoices\InvoiceMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

final class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('inv.pdf', '%PDF-1.4 test');
    }

    public function test_returns_501_when_invoice_mail_not_configured(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)
            ->post('/invoices/send', ['to' => 'a@b.test', 'pdf' => $this->pdf()])
            ->assertStatus(501);
    }

    public function test_sends_when_configured(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $s = UserSetting::for($user->id);
        $s->forceFill([
            'invoice_mail_enabled' => true,
            'invoice_smtp_host' => 'smtp.example.test',
            'invoice_from_email' => 'me@example.test',
        ])->save();
        app()->forgetInstance('memo.user_setting.'.$user->id);

        $mock = Mockery::mock(InvoiceMailer::class);
        $mock->shouldReceive('configured')->andReturnTrue();
        $mock->shouldReceive('send')->once()->withArgs(function ($settings, $to, $subject, $body, $pdf, $file) {
            return $to === 'client@example.test' && $subject === 'Invoice 7' && str_contains($pdf, '%PDF');
        });
        $this->app->instance(InvoiceMailer::class, $mock);

        $this->actingAs($user)
            ->post('/invoices/send', [
                'to' => 'client@example.test',
                'subject' => 'Invoice 7',
                'body' => 'Hello',
                'pdf' => $this->pdf(),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_rejects_bad_recipient_and_missing_pdf(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $s = UserSetting::for($user->id);
        $s->forceFill(['invoice_mail_enabled' => true, 'invoice_smtp_host' => 'smtp.example.test', 'invoice_from_email' => 'me@example.test'])->save();
        app()->forgetInstance('memo.user_setting.'.$user->id);

        $this->actingAs($user)->post('/invoices/send', ['to' => 'not-an-email', 'pdf' => $this->pdf()])->assertSessionHasErrors('to');
        $this->actingAs($user)->post('/invoices/send', ['to' => 'a@b.test'])->assertSessionHasErrors('pdf');
    }

    public function test_blocked_when_finance_module_disabled(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes']]);
        $this->actingAs($user)
            ->post('/invoices/send', ['to' => 'a@b.test', 'pdf' => $this->pdf()])
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
