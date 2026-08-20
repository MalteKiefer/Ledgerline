<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Standing guards over the shape of the public API surface.
 *
 * These exist because the expensive mistakes in this codebase have not been
 * clever ones — they were a control that quietly stopped applying. An endpoint
 * that loses its auth middleware, or a route that never reaches openapi.yaml,
 * looks exactly like working code until someone reads it months later.
 */
class ApiSurfaceGuardTest extends TestCase
{
    /**
     * Endpoints that are deliberately reachable without a bearer token, each
     * with the credential that replaces authentication. ADDING A LINE HERE IS A
     * SECURITY DECISION: it publishes an endpoint to the internet. It belongs in
     * the CLAUDE.md security register together with its compensating control.
     *
     * @var list<string>
     */
    private const PUBLIC_ENDPOINTS = [
        // Authentication itself — these mint or reset the credential.
        'auth/login', 'auth/register', 'auth/forgot-password', 'auth/reset-password',
        'auth/pair', 'auth/pair/collect', 'auth/passkey/options', 'auth/passkey/verify',
        // Capability-in-the-URL: the token IS the credential (password/expiry gated).
        'file-share/{token}', 'file-share/{token}/manifest', 'file-share/{token}/unlock',
        'file-share/{token}/file/{file}/raw',
        'gallery-share/{token}', 'gallery-share/{token}/manifest', 'gallery-share/{token}/unlock',
        'gallery-share/{token}/photo/{photo}/raw', 'gallery-share/{token}/photo/{photo}/thumb',
        'gallery-share/{token}/photo/{photo}/preview',
        'gallery-upload/{token}', 'upload-link/{token}',
        // Single-use, hashed-at-rest invite link that sets a password.
        'invite/{invite}/{token}',
    ];

    public function test_every_api_route_requires_authentication_or_is_a_declared_public_endpoint(): void
    {
        $unauthenticated = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $authenticated = false;
            foreach ($middleware as $m) {
                if (is_string($m) && (str_contains($m, 'auth:sanctum') || str_contains($m, 'Authenticate:sanctum'))) {
                    $authenticated = true;
                    break;
                }
            }
            $path = substr($uri, strlen('api/v1/'));
            if (! $authenticated && ! in_array($path, self::PUBLIC_ENDPOINTS, true)) {
                $unauthenticated[] = $route->methods()[0].' '.$uri;
            }
        }

        $this->assertSame([], array_values(array_unique($unauthenticated)),
            'New unauthenticated /api/v1 endpoint(s). If that is intended, add the path to PUBLIC_ENDPOINTS '
            .'and record the decision in the CLAUDE.md security register with its compensating control.');
    }

    /**
     * openapi.yaml is the contract the mobile clients build against, and it is
     * hand-edited. A broken flow mapping once shipped and stayed unnoticed for
     * releases, because every check here reads the file as text and greps it —
     * a malformed spec looked exactly like a well-formed one.
     *
     * There is no YAML parser in this runtime, so this asserts the invariant the
     * file actually holds: every line closes the braces and brackets it opens.
     * That is what a dropped `}` breaks, and it is what nothing else catches.
     */
    /**
     * Every route of a toggleable module carries the module gate. Without it a
     * user whose admin switched the module off keeps reaching its data — the gate
     * is one forgotten group away from being decorative.
     *
     * The exceptions are deliberate and named: admin workspace settings, the
     * crypto keyring (shared by Mail and Files, so gating it on Mail would lock
     * out Files encryption), and token-addressed endpoints with no session to
     * gate against.
     */
    public function test_module_routes_carry_the_module_gate(): void
    {
        $ungated = [];
        foreach (Route::getRoutes() as $route) {
            $action = (string) ($route->getAction('controller') ?? '');
            $controller = class_basename(strtok($action, '@'));
            if (! preg_match('/^(Notes|Files|Gallery|Contact|Calendar|Mail|Finance)[A-Za-z]*Controller$/', $controller)) {
                continue;
            }
            $uri = $route->uri();
            if (str_contains($uri, '/admin/')                        // workspace settings, admin-gated
                || str_starts_with($uri, 'crypto/')                  // keyring shared by Mail + Files
                || str_starts_with($uri, 'api/v1/crypto/')
                || str_contains($uri, 'upload-link/')                // token is the credential, no session
                || str_contains($uri, 'birthdays/')
                || $uri === 'settings/files') {                      // personal preference, no module data
                continue;
            }
            // The gate lives on the route group, so resolve the way route:list does.
            $middleware = implode(' ', app('router')->gatherRouteMiddleware($route));
            if (! str_contains($middleware, 'EnsureModule')) {
                $ungated[] = $route->methods()[0].' '.$uri;
            }
        }

        $this->assertSame([], $ungated,
            'Route(s) of a toggleable module without the module gate. Add module:<key>, or — if the endpoint is '
            .'deliberately reachable with the module off — list it here with the reason.');
    }

    public function test_openapi_flow_mappings_are_balanced(): void
    {
        $lines = explode("\n", (string) file_get_contents(base_path('../openapi.yaml')));
        $offenders = [];
        foreach ($lines as $i => $line) {
            // Delimiters inside quoted scalars are text, not structure.
            $stripped = preg_replace(['/"(?:[^"\\\\]|\\\\.)*"/', "/'(?:[^']|'')*'/"], ['""', "''"], $line) ?? $line;
            if (substr_count($stripped, '{') !== substr_count($stripped, '}')
                || substr_count($stripped, '[') !== substr_count($stripped, ']')) {
                $offenders[] = ($i + 1).': '.trim($line);
            }
        }

        $this->assertSame([], $offenders,
            'Unbalanced flow mapping in openapi.yaml — the spec will not parse for any client generating code from it.');
    }

    public function test_every_api_route_is_documented_in_openapi(): void
    {
        $spec = (string) file_get_contents(base_path('../openapi.yaml'));
        preg_match_all('/^  (\/\S+):\s*$/m', $spec, $m);
        $documented = array_flip($m[1]);

        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $path = '/'.substr($uri, strlen('api/v1/'));
            if (! isset($documented[$path])) {
                $missing[] = $path;
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            'Route(s) missing from openapi.yaml. The spec is the contract the mobile clients build against; '
            .'a route and its documentation change in the same commit.');
    }
}
