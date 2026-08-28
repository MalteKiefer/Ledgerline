<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class FinanceModuleBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_module_provider_is_registered(): void
    {
        $this->assertNotEmpty(app()->getProviders('App\\Modules\\Finance\\FinanceServiceProvider'));
    }

    public function test_finance_v2_health_returns_the_module_contract_for_a_finance_enabled_user(): void
    {
        $this->assertTrue(Route::has('api.finance-v2.health'));

        $user = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $token = $user->createToken('device', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.finance-v2.health'))
            ->assertOk()
            ->assertExactJson(['module' => 'finance', 'schemaVersion' => 1]);
    }

    public function test_finance_v2_health_requires_authentication(): void
    {
        $this->assertTrue(Route::has('api.finance-v2.health'));

        $this->getJson(route('api.finance-v2.health'))->assertUnauthorized();
    }

    public function test_finance_v2_health_rejects_users_without_finance_enabled(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $token = $user->createToken('device', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.finance-v2.health'))
            ->assertForbidden();
    }

    public function test_finance_domain_source_has_no_http_or_laravel_persistence_dependencies(): void
    {
        $domainPath = app_path('Modules/Finance/Domain');
        $this->assertDirectoryExists($domainPath);

        $forbiddenNamespaces = [
            'Illuminate\\Http',
            'Illuminate\\Database\\Eloquent',
            'Illuminate\\Support\\Facades',
            'Symfony\\Component\\HttpFoundation',
        ];
        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($domainPath, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source = file_get_contents($file->getPathname());

                foreach ($forbiddenNamespaces as $forbiddenNamespace) {
                    if (is_string($source) && str_contains($source, $forbiddenNamespace)) {
                        $violations[] = $file->getPathname().': '.$forbiddenNamespace;
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
