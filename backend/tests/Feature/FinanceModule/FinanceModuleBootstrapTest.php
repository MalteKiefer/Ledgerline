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

    public function test_project_domain_and_application_sources_do_not_depend_on_legacy_or_framework_layers(): void
    {
        $roots = [
            app_path('Modules/Finance/Domain/Projects'),
            app_path('Modules/Finance/Application/Commands/Projects'),
            app_path('Modules/Finance/Application/Queries/Projects'),
            app_path('Modules/Finance/Application/DTOs/Projects'),
            app_path('Modules/Finance/Application/Ports/Projects'),
            app_path('Modules/Finance/Application/Services/Projects'),
        ];
        $forbidden = [
            'App\\Http\\Controllers',
            'App\\Models\\FinanceProject',
            'App\\Models\\FinanceProjectTask',
            'App\\Models\\FinanceTimeEntry',
            'App\\Models\\FinanceQuote',
            'App\\Models\\Invoice',
            'App\\Models\\FileEntry',
            'App\\Models\\GalleryPhoto',
            'Illuminate\\Http',
            'Illuminate\\Database\\Eloquent',
            'Illuminate\\Support\\Facades',
        ];
        $namespacePrefixes = [
            'App\\Http\\Controllers',
            'Illuminate\\Http',
            'Illuminate\\Database\\Eloquent',
            'Illuminate\\Support\\Facades',
        ];
        $violations = [];

        $this->assertDirectoryExists($roots[0]);
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                if (! is_string($source)) {
                    continue;
                }

                foreach ($forbidden as $symbol) {
                    $suffix = in_array($symbol, $namespacePrefixes, true)
                        ? '(?=\\\\|[^A-Za-z0-9_\\\\]|$)'
                        : '(?![A-Za-z0-9_\\\\])';
                    $pattern = '/(?<![A-Za-z0-9_\\\\])'.preg_quote($symbol, '/').$suffix.'/';
                    if (preg_match($pattern, $source) === 1) {
                        $violations[] = $file->getPathname().': '.$symbol;
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
