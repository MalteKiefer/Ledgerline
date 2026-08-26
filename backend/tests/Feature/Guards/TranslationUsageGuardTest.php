<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use Tests\TestCase;

/**
 * Every literal translation key the SPA asks for actually exists.
 *
 * The parity guard proves EN/DE/RU define the same keys; it cannot notice a key
 * that exists in none of them. That failure is invisible to every gate we have —
 * typecheck, lint and build all pass, because `t('invoices.positions')` is a
 * valid call whatever the argument is — and it surfaces as the raw key path in
 * front of the user. It reached a screenshot once; this is so it cannot again.
 *
 * Only LITERAL keys are checked. A key assembled at runtime
 * (`t('invoices.product_kind_' + p.kind)`) cannot be resolved statically, and
 * guessing at its prefixes is how a deletion sweep once removed live keys.
 */
class TranslationUsageGuardTest extends TestCase
{
    public function test_every_literal_translation_key_used_by_the_spa_exists(): void
    {
        $langDir = base_path('lang/en');
        $srcDir = dirname(base_path()).'/frontend/src';

        if (! is_dir($srcDir)) {
            $this->markTestSkipped('The frontend sources are not present in this checkout.');
        }

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        foreach ((array) glob($langDir.'/*.php') as $file) {
            if (! is_string($file)) {
                continue;
            }
            $loaded = require $file;
            $groups[basename($file, '.php')] = is_array($loaded) ? $loaded : [];
        }

        $missing = [];
        foreach ($this->sourceFiles($srcDir) as $path) {
            $code = (string) file_get_contents($path);
            // t('group.key') / t("group.key") — the single-argument literal form.
            if (preg_match_all('/\bt\(\s*[\'"]([a-zA-Z0-9_]+)\.([a-zA-Z0-9_.]+)[\'"]\s*\)/', $code, $m, PREG_SET_ORDER) < 1) {
                continue;
            }
            foreach ($m as $hit) {
                [$whole, $group, $key] = $hit;
                unset($whole);
                if (! array_key_exists($group, $groups)) {
                    // Not a lang file we ship — a namespaced key from a package,
                    // or a false positive on some other one-letter function.
                    continue;
                }
                if (data_get($groups[$group], $key) === null) {
                    $missing[] = $group.'.'.$key.'  ('.basename($path).')';
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        $this->assertSame([], $missing, "The SPA asks for translation keys that no language defines, so the raw key path is what the user sees:\n".implode("\n", $missing));
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(string $dir): array
    {
        $out = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
