<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushJob;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notification creation fans out to the user's registered push endpoints through
 * the AppNotification::record choke point + SendPushJob.
 */
class SendPushJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_notification_dispatches_the_push_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        AppNotification::record($user->id, 'info', 'Hi', 'there', 'reminder');

        Queue::assertPushed(SendPushJob::class);
    }

    public function test_job_posts_payload_to_registered_endpoints(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $user = User::factory()->create();
        $user->createToken('phone', ['device'])->accessToken
            ->forceFill(['push_endpoint' => 'https://push.example/topic/x'])->save();

        $n = AppNotification::create([
            'user_id' => $user->id, 'level' => 'warning', 'category' => 'invoice', 'title' => 'Due', 'body' => 'Pay up',
        ]);
        (new SendPushJob($n))->handle();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://push.example/topic/x'
                && $body['category'] === 'invoice'
                && $body['level'] === 'warning'
                && $body['title'] === 'Due'
                && $body['body'] === 'Pay up';
        });
    }

    public function test_job_skips_a_category_the_user_disabled(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $user = User::factory()->create();
        $user->createToken('phone', ['device'])->accessToken
            ->forceFill(['push_endpoint' => 'https://push.example/x'])->save();
        UserSetting::for($user->id)->update(['notification_prefs' => ['invoice' => ['push' => false]]]);

        $n = AppNotification::create([
            'user_id' => $user->id, 'level' => 'info', 'category' => 'invoice', 'title' => 'Due', 'body' => null,
        ]);
        (new SendPushJob($n))->handle();

        Http::assertNothingSent();
    }

    public function test_job_prunes_an_endpoint_that_is_gone(): void
    {
        Http::fake(['*' => Http::response('', 410)]);
        $user = User::factory()->create();
        $token = $user->createToken('phone', ['device'])->accessToken;
        $token->forceFill(['push_endpoint' => 'https://push.example/dead'])->save();

        $n = AppNotification::create([
            'user_id' => $user->id, 'level' => 'info', 'category' => 'reminder', 'title' => 'X', 'body' => null,
        ]);
        (new SendPushJob($n))->handle();

        $this->assertNull($token->fresh()->push_endpoint);
    }
}
