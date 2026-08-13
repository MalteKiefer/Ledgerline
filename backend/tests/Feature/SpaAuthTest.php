<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_me_works(): void
    {
        $u = User::factory()->create(['email' => 'a@b.de', 'password' => 'supersecret12']);
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'a@b.de', 'password' => 'supersecret12']);
        $r->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'modules']]);
        $token = $r->json('token');
        $this->assertIsString($token);
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.email', 'a@b.de');
    }

    public function test_native_login_registers_a_device_with_metadata(): void
    {
        User::factory()->create(['email' => 'a@b.de', 'password' => 'supersecret12']);
        $r = $this->postJson('/api/v1/auth/login', [
            'email' => 'a@b.de', 'password' => 'supersecret12',
            'device_name' => 'Google Pixel 9', 'install_id' => 'abc123def456',
            'app_version' => '1.4.2', 'os_version' => 'Android 16',
        ])->assertOk();
        $token = $r->json('token');
        $this->assertIsString($token);
        // The device appears under "Connected devices" with its metadata.
        $devices = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/devices')->assertOk()->json('devices');
        $this->assertCount(1, $devices);
        $this->assertSame('Google Pixel 9', $devices[0]['name']);
        // Second login on the SAME install_id replaces the device (no stacking).
        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@b.de', 'password' => 'supersecret12',
            'device_name' => 'Google Pixel 9', 'install_id' => 'abc123def456',
        ])->assertOk();
        $this->assertSame(1, User::where('email', 'a@b.de')->first()->tokens()->count());
    }

    public function test_browser_login_stays_a_plain_web_token(): void
    {
        $u = User::factory()->create(['email' => 'c@b.de', 'password' => 'supersecret12']);
        $this->postJson('/api/v1/auth/login', ['email' => 'c@b.de', 'password' => 'supersecret12'])->assertOk();
        $this->assertSame('web', $u->tokens()->first()->name);
    }

    public function test_bad_password_fails(): void
    {
        User::factory()->create(['email' => 'a@b.de', 'password' => 'supersecret12']);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.de', 'password' => 'wrong'])->assertStatus(422);
    }

    public function test_query_token_middleware_authorizes_media(): void
    {
        $u = User::factory()->create(['password' => 'supersecret12']);
        $token = $u->createToken('web', ['device'])->plainTextToken;
        // avatar 404 (none stored) proves auth passed via ?_token (else 401)
        $this->getJson('/api/v1/avatar?_token='.$token)->assertStatus(404);
    }

    public function test_media_without_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/avatar')->assertStatus(401);
    }
}
