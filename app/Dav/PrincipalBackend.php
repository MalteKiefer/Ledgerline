<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

/**
 * One principal per authenticated user, derived from the request's logged-in
 * user (WebDavAuth logs them in on successful HTTP Basic auth). The principal id
 * is the user's e-mail. Only ever exposes the authenticated user's own principal
 * — never another user's — so DAVACL cannot address a foreign principal. Group
 * membership is unused (single-user principals), so those methods are inert.
 */
class PrincipalBackend implements BackendInterface
{
    public function getPrincipalsByPrefix($prefixPath): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }
        $principal = $this->principal($user);
        if (! str_starts_with($principal['uri'], rtrim($prefixPath, '/'))) {
            return [];
        }

        return [$principal];
    }

    public function getPrincipalByPath($path): ?array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }
        // Only resolve the authenticated user's own principal; a request for any
        // other principal path returns null (no cross-principal addressing).
        return basename($path) === (string) $user->email ? $this->principal($user) : null;
    }

    public function updatePrincipal($path, PropPatch $propPatch): void {}

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof'): array
    {
        return [];
    }

    public function findByUri($uri, $principalPrefix): ?string
    {
        return null;
    }

    public function getGroupMemberSet($principal): array
    {
        return [];
    }

    public function getGroupMembership($principal): array
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members): void {}

    /**
     * @return array{uri: string, '{DAV:}displayname': string, '{http://sabredav.org/ns}email-address': string}
     */
    private function principal(User $user): array
    {
        return [
            'uri' => 'principals/'.$user->email,
            '{DAV:}displayname' => (string) ($user->name ?: $user->email),
            '{http://sabredav.org/ns}email-address' => (string) $user->email,
        ];
    }
}
