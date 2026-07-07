<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_triggers_dispatch_allowlisted_commands(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $this->post(route('settings.files.reindex'))->assertRedirect();
        $this->postJson(route('bookmarks.check-links'))->assertOk();
        $this->post(route('settings.calendar.refresh-subscriptions'))->assertRedirect();

        Queue::assertPushed(RunCommand::class, 3);
        Queue::assertPushed(RunCommand::class, fn (RunCommand $j) => $j->command === 'files:extract-text' && ($j->options['--all'] ?? false));
        Queue::assertPushed(RunCommand::class, fn (RunCommand $j) => $j->command === 'bookmarks:check-links');
        Queue::assertPushed(RunCommand::class, fn (RunCommand $j) => $j->command === 'calendar:refresh-subscriptions');
    }

    public function test_run_command_rejects_unlisted(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        new RunCommand('rm:everything');
    }
}
