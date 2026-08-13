<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rate limiting is load-bearing on the internet-facing deployment. These tests
 * guard against a regression to the disabled (NoThrottle) state AND against the
 * boot-churn trap that previously 500'd /login ("Rate limiter [x] is not defined").
 */
final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_named_limiters_are_defined_at_request_time(): void
    {
        // Force the deferred CacheServiceProvider + a settings/session resolution
        // to mimic the historical boot-churn, then assert the limiters survived.
        $this->app->make('cache')->store();
        $limiter = $this->app->make(RateLimiter::class);

        foreach (['login', 'two-factor', 'fortify', 'auth-pair', 'dav', 'share-unlock', 'invite'] as $name) {
            $this->assertNotNull($limiter->limiter($name), "Named rate limiter [{$name}] is not defined");
        }
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        // login limiter = 5/min per (email|ip). 6th attempt → 429.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password']);
            $this->assertNotSame(429, $response->getStatusCode(), "attempt {$i} should not be throttled");
        }

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_two_factor_challenge_is_throttled(): void
    {
        // The 6-digit TOTP challenge must NOT be unlimited (online brute-force).
        // two-factor limiter = 5/min. The throttle runs before the controller.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(429);
    }
}
