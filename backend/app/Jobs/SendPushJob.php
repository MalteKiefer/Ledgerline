<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\OutboundUrl;
use App\Support\Redactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fan a freshly-created notification-centre row out to the user's registered
 * per-device UnifiedPush endpoints (self-hosted ntfy topic URLs). Enqueued from
 * the single choke point AppNotification::record().
 *
 * Best-effort by design: the durable record is the notification-centre row, so a
 * transient push failure is not retried (that would re-POST to the endpoints that
 * already succeeded and duplicate the push). Dead endpoints (404/410 = gone) are
 * pruned. Every POST is SSRF-guarded via OutboundUrl (the endpoint host is
 * user-supplied): IP-pinned, link-local/metadata blocked, no redirects, https only.
 */
class SendPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Single attempt — see the class note on why we don't retry. */
    public int $tries = 1;

    public function __construct(private readonly AppNotification $notification) {}

    public function handle(): void
    {
        $user = User::query()->find($this->notification->user_id);
        if ($user === null) {
            return;
        }

        // Respect the user's per-category push opt-out (the notification-centre
        // row still exists; only the push fan-out is suppressed).
        if (! UserSetting::for($user->id)->pushEnabled((string) $this->notification->category)) {
            return;
        }

        /** @var iterable<PersonalAccessToken> $devices */
        $devices = $user->tokens()->whereNotNull('push_endpoint')->get();

        $payload = [
            'id' => $this->notification->id,
            'category' => $this->notification->category,
            'level' => $this->notification->level,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
        ];

        foreach ($devices as $device) {
            $endpoint = is_string($device->push_endpoint) ? trim($device->push_endpoint) : '';
            // Defence in depth: only https leaves the server (also enforced at
            // registration). A malformed/non-https stored value is skipped.
            if (! str_starts_with($endpoint, 'https://')) {
                continue;
            }
            $this->deliver($device, $endpoint, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(PersonalAccessToken $device, string $endpoint, array $payload): void
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            // IP-pinned, redirect-free, link-local/metadata-blocked POST.
            $response = OutboundUrl::client($endpoint, 15)
                ->withBody($body, 'application/json')
                ->post($endpoint);

            // Endpoint gone (distributor removed / topic deleted): prune it so we
            // stop trying. Anything else is left in place (may be transient).
            if (in_array($response->status(), [404, 410], true)) {
                $device->forceFill(['push_endpoint' => null])->save();
            }
        } catch (Throwable $e) {
            // Never let one bad endpoint break the fan-out (or the caller). Log
            // only the scheme+host of the endpoint — the full URL (path = the
            // secret ntfy topic capability) must never reach the log; and the
            // exception message can embed the URL, so redact it too.
            Log::warning('push delivery failed', [
                'token_id' => $device->getKey(),
                'host' => parse_url($endpoint, PHP_URL_HOST),
                'error' => Redactor::redact($e->getMessage()),
            ]);
        }
    }
}
