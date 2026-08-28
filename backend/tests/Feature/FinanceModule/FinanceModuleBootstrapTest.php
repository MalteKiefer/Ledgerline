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

    private const PROJECT_FORBIDDEN_MODELS = [
        'App\\Models\\FinanceProject',
        'App\\Models\\FinanceProjectTask',
        'App\\Models\\FinanceTimeEntry',
        'App\\Models\\FinanceQuote',
        'App\\Models\\Invoice',
        'App\\Models\\FileEntry',
        'App\\Models\\GalleryPhoto',
    ];

    private const PROJECT_FORBIDDEN_NAMESPACE_PREFIXES = [
        'App\\Http\\Controllers',
        'Illuminate\\Http',
        'Illuminate\\Database\\Eloquent',
        'Illuminate\\Support\\Facades',
    ];

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
                if (! is_string($source)) {
                    continue;
                }

                foreach ($this->phpReferencedNames($source) as $name) {
                    foreach ($forbiddenNamespaces as $forbiddenNamespace) {
                        if ($name === $forbiddenNamespace || str_starts_with($name, $forbiddenNamespace.'\\')) {
                            $violations[] = $file->getPathname().': '.$name;
                            break;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_project_sources_enforce_dependency_direction_across_domain_application_and_infrastructure(): void
    {
        $this->assertDirectoryExists(app_path('Modules/Finance/Domain/Projects'));
        $this->assertSame([], $this->projectDependencyViolations($this->financeModuleSources()));
    }

    public function test_project_dependency_guard_detects_code_mutations_without_comment_string_or_module_false_positives(): void
    {
        $sources = [
            'Domain/Projects/LeadingLegacy.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Domain\Projects;
                $commentExample = 'App\Models\Invoice';
                // App\Models\FinanceQuote is documentation, not a dependency.
                \App\Models\FinanceProject::query();
                PHP,
            'Domain/Projects/SimilarName.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Domain\Projects;
                use App\Models\FinanceProjectProjection;
                use App\Models\InvoiceLine;
                PHP,
            'Application/Commands/Projects/FacadeLeak.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Application\Commands\Projects;
                use Illuminate\Support\Facades\DB;
                PHP,
            'Application/DTOs/Projects/GroupedLegacy.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Application\DTOs\Projects;
                use App\Models\{FileEntry, FinanceProjectProjection};
                PHP,
            'Infrastructure/Persistence/EloquentProjectLeak.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Persistence;
                use App\Models\Invoice;
                PHP,
            'Infrastructure/Persistence/Models/ProjectRecord.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Persistence\Models;
                use Illuminate\Database\Eloquent\Model;
                PHP,
            'Infrastructure/Documents/LegacyInvoiceDocumentSource.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Documents;
                use App\Models\Invoice;
                PHP,
            'Infrastructure/Compatibility/LegacyProjectReferenceResolver.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Compatibility;
                use App\Models\FinanceProject;
                PHP,
            'Infrastructure/Integrations/Quotes/FinanceQuoteProjectTarget.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Integrations\Quotes;
                use App\Models\FinanceQuote;
                PHP,
            'Infrastructure/Persistence/EloquentQuoteRepository.php' => <<<'PHP'
                <?php
                namespace App\Modules\Finance\Infrastructure\Persistence;
                use App\Models\FinanceQuote;
                PHP,
        ];

        $this->assertSame([
            'Application/Commands/Projects/FacadeLeak.php: Illuminate\Support\Facades\DB',
            'Application/DTOs/Projects/GroupedLegacy.php: App\Models\FileEntry',
            'Domain/Projects/LeadingLegacy.php: App\Models\FinanceProject',
            'Infrastructure/Documents/LegacyInvoiceDocumentSource.php: App\Models\Invoice',
            'Infrastructure/Persistence/EloquentProjectLeak.php: App\Models\Invoice',
        ], $this->projectDependencyViolations($sources));
    }

    /** @return array<string, string> */
    private function financeModuleSources(): array
    {
        $modulePath = app_path('Modules/Finance');
        $sources = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePath, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (is_string($source)) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($modulePath))), '/');
                $sources[$relative] = $source;
            }
        }

        return $sources;
    }

    /**
     * @param  array<string, string>  $sources
     * @return list<string>
     */
    private function projectDependencyViolations(array $sources): array
    {
        $violations = [];

        foreach ($sources as $relativePath => $source) {
            $names = $this->phpReferencedNames($source);
            $scope = $this->projectDependencyScope($relativePath, $names);
            if ($scope === null || $scope === 'allowed_infrastructure') {
                continue;
            }

            foreach ($names as $name) {
                if (in_array($name, self::PROJECT_FORBIDDEN_MODELS, true)) {
                    $violations[] = $relativePath.': '.$name;

                    continue;
                }

                if ($scope !== 'core') {
                    continue;
                }

                foreach (self::PROJECT_FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
                    if ($name === $prefix || str_starts_with($name, $prefix.'\\')) {
                        $violations[] = $relativePath.': '.$name;
                        break;
                    }
                }
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    /** @param list<string> $referencedNames */
    private function projectDependencyScope(string $relativePath, array $referencedNames): ?string
    {
        $path = str_replace('\\', '/', $relativePath);
        if (str_starts_with($path, 'Domain/Projects/')
            || preg_match('#^Application/(Commands|Queries|DTOs|Ports|Services)/Projects/#', $path) === 1) {
            return 'core';
        }

        if (! str_starts_with($path, 'Infrastructure/')) {
            return null;
        }

        $isProjectInfrastructure = str_contains($path, '/Projects/')
            || str_starts_with($path, 'Infrastructure/Documents/')
            || str_contains(pathinfo($path, PATHINFO_FILENAME), 'Project')
            || array_any(
                $referencedNames,
                static fn (string $name): bool => str_contains($name, '\\Projects\\'),
            );
        if (! $isProjectInfrastructure) {
            return null;
        }

        if (preg_match('#^Infrastructure/(Compatibility|Integrations)/#', $path) === 1) {
            return 'allowed_infrastructure';
        }

        return 'infrastructure';
    }

    /** @return list<string> */
    private function phpReferencedNames(string $source): array
    {
        $tokens = token_get_all($source);
        $names = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $names[] = ltrim($token[1], '\\');
            }

            if (is_array($token) && $token[0] === T_USE) {
                $names = [...$names, ...$this->phpUseNames($tokens, $index)];
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @param  list<array{int, string, int}|string>  $tokens
     * @return list<string>
     */
    private function phpUseNames(array $tokens, int $useIndex): array
    {
        $names = [];
        $prefix = '';
        $current = '';
        $grouped = false;
        $alias = false;

        for ($index = $useIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_string($token)) {
                if ($token === '(') {
                    return [];
                }
                if ($token === '{') {
                    $prefix = trim($current, '\\');
                    $current = '';
                    $grouped = true;
                } elseif ($token === ',' || $token === '}' || $token === ';') {
                    if ($current !== '') {
                        $names[] = ltrim(($grouped && $prefix !== '' ? $prefix.'\\' : '').$current, '\\');
                    }
                    $current = '';
                    $alias = false;
                    if ($token === ';') {
                        break;
                    }
                }

                continue;
            }

            if ($token[0] === T_AS) {
                $alias = true;

                continue;
            }
            if ($alias || in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING, T_NS_SEPARATOR], true)) {
                $current .= $token[1];
            }
        }

        return $names;
    }
}
