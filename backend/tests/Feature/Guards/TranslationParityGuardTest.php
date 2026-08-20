<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use Tests\TestCase;

/**
 * EN/DE/RU must expose the same keys. A missing key does not fail loudly — the
 * interface simply shows the raw key path to whoever uses that language, which
 * is why this needs a machine to check it rather than a reviewer.
 */
class TranslationParityGuardTest extends TestCase
{
    public function test_every_language_defines_the_same_keys(): void
    {
        $locales = ['en', 'de', 'ru'];
        $keysPerLocale = [];
        $files = [];

        foreach ($locales as $locale) {
            foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
                $name = basename($file, '.php');
                $files[$name] = true;
                $data = require $file;
                $keysPerLocale[$locale][$name] = is_array($data) ? $this->leafKeys($data) : [];
            }
        }

        $problems = [];
        foreach (array_keys($files) as $name) {
            foreach ($locales as $locale) {
                if (! isset($keysPerLocale[$locale][$name])) {
                    $problems[] = "lang/{$locale}/{$name}.php is missing entirely";
                }
            }
            $reference = $keysPerLocale['en'][$name] ?? [];
            foreach (['de', 'ru'] as $locale) {
                $own = $keysPerLocale[$locale][$name] ?? [];
                foreach (array_diff($reference, $own) as $key) {
                    $problems[] = "lang/{$locale}/{$name}.php is missing {$key}";
                }
                foreach (array_diff($own, $reference) as $key) {
                    $problems[] = "lang/{$locale}/{$name}.php has {$key}, which en does not";
                }
            }
        }

        $this->assertSame([], array_slice($problems, 0, 25), 'Translation keys drifted between en/de/ru.');
    }

    /**
     * Leaf key paths only — counting container keys hides a nested key that one
     * language defines and another does not.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function leafKeys(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out = [...$out, ...$this->leafKeys($value, $path)];

                continue;
            }
            $out[] = $path;
        }

        return $out;
    }
}
