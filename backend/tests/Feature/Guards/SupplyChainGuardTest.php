<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use Tests\TestCase;

/**
 * Supply-chain pins. A floating tag means the thing that runs in production, or
 * the thing that holds a GHCR push token in CI, can change under us without a
 * commit — which is precisely the window a compromised upstream needs.
 */
class SupplyChainGuardTest extends TestCase
{
    public function test_every_container_image_is_pinned_to_a_digest(): void
    {
        $unpinned = [];
        foreach (['../Dockerfile', '../docker-compose.yml'] as $relative) {
            $path = base_path($relative);
            if (! is_file($path)) {
                continue;
            }
            foreach (explode("\n", (string) file_get_contents($path)) as $n => $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                // `image: repo:tag@sha256:…` in compose, `FROM repo:tag@sha256:…`
                // in the Dockerfile. A build stage referenced by name (FROM x AS y
                // / FROM y) carries no registry reference and needs no digest.
                if (preg_match('/^(?:image:\s*|FROM\s+)(\S+)/i', $trimmed, $m) !== 1) {
                    continue;
                }
                $ref = $m[1];
                if (str_starts_with($ref, '$') || ! str_contains($ref, ':')) {
                    continue; // build-arg indirection or a local stage name
                }
                if (! str_contains($ref, '@sha256:')) {
                    $unpinned[] = basename($path).':'.($n + 1).' '.$ref;
                }
            }
        }

        $this->assertSame([], $unpinned,
            'Container image without a digest pin. Resolve the digest and pin it (repo:tag@sha256:…).');
    }

    public function test_every_github_action_is_pinned_to_a_commit_sha(): void
    {
        $unpinned = [];
        foreach (glob(base_path('../.github/workflows/*.yml')) ?: [] as $file) {
            preg_match_all('/uses:\s*(\S+)/', (string) file_get_contents($file), $m);
            foreach ($m[1] as $ref) {
                if (str_starts_with($ref, './')) {
                    continue; // local composite action, versioned with this repo
                }
                if (preg_match('/@[0-9a-f]{40}$/', $ref) !== 1) {
                    $unpinned[] = basename($file).' → '.$ref;
                }
            }
        }

        $this->assertSame([], $unpinned,
            'GitHub Action referenced by tag. A tag is mutable, and these workflows hold a GHCR push token — pin the commit sha.');
    }
}
