<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VirusTotalApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function headers(): array
    {
        $token = User::factory()->admin()->create()->createToken('test-device', ['device'])->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_admin_can_only_save_a_key_accepted_by_virustotal(): void
    {
        Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response(['data' => []], 200)]);

        $this->putJson('/api/v1/admin/virustotal', ['api_key' => str_repeat('a', 64)], $this->headers())
            ->assertOk()
            ->assertJson(['configured' => true, 'verified' => true]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.virustotal.com/api/v3/files/275a021bbfb6489e54d471899f7db9d18e5a807f17f81f4b6b3cfa861fc5b3ec'
                && $request->hasHeader('x-apikey', str_repeat('a', 64));
        });
        $this->assertSame(str_repeat('a', 64), AppSettings::current()->virustotal_api_key);
    }

    public function test_rejected_key_is_not_saved_over_the_existing_key(): void
    {
        $settings = AppSettings::current();
        $settings->update(['virustotal_api_key' => str_repeat('a', 64)]);
        Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response([], 401)]);

        $this->putJson('/api/v1/admin/virustotal', ['api_key' => str_repeat('b', 64)], $this->headers())
            ->assertUnprocessable()
            ->assertJson(['error' => 'virustotal_invalid_api_key']);

        $this->assertSame(str_repeat('a', 64), AppSettings::current()->virustotal_api_key);
    }
}
