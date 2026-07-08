<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_triggers_dispatch_allowlisted_commands(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $this->postJson(route('bookmarks.check-links'))->assertOk();
        $this->post(route('settings.calendar.refresh-subscriptions'))->assertRedirect();

        Queue::assertPushed(RunCommand::class, 2);
        Queue::assertPushed(RunCommand::class, fn (RunCommand $j) => $j->command === 'bookmarks:check-links');
        Queue::assertPushed(RunCommand::class, fn (RunCommand $j) => $j->command === 'calendar:refresh-subscriptions');
    }

    public function test_run_command_rejects_unlisted(): void
    {
        $this->expectException(HttpException::class);
        new RunCommand('rm:everything');
    }
}
