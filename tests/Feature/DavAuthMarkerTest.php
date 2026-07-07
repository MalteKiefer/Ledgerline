<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AuthBackend;
use App\Models\DavCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DavAuthMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marker_is_set_only_after_a_real_bcrypt_check(): void
    {
        $user = User::factory()->create();
        DavCredential::create(['user_id' => $user->id, 'username' => 'dav-x', 'password_hash' => bcrypt('correct-horse')]);
        $auth = app(AuthBackend::class);
        $call = (new \ReflectionMethod($auth, 'validateUserPass'));
        $call->setAccessible(true);

        // Wrong password: no marker (attacker cannot forge the generous bucket).
        $this->assertFalse($call->invoke($auth, 'dav-x', 'wrong'));
        $this->assertNull(Cache::get(AuthBackend::authMarkerKey('dav-x', 'wrong')));

        // Correct password: marker set, so the rate limiter grants the high quota.
        $this->assertTrue($call->invoke($auth, 'dav-x', 'correct-horse'));
        $this->assertTrue(Cache::get(AuthBackend::authMarkerKey('dav-x', 'correct-horse')));
    }
}
