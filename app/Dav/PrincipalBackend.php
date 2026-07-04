<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\DavCredential;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

/**
 * One principal per DAV credential. Group membership is unused (single-user
 * principals), so those methods are inert.
 */
class PrincipalBackend implements BackendInterface
{
    public function getPrincipalsByPrefix($prefixPath): array
    {
        $out = [];
        foreach (DavCredential::all() as $credential) {
            $principal = $this->principal($credential);
            if (str_starts_with($principal['uri'], rtrim($prefixPath, '/'))) {
                $out[] = $principal;
            }
        }

        return $out;
    }

    public function getPrincipalByPath($path): ?array
    {
        $username = basename($path);
        $credential = DavCredential::where('username', $username)->first();

        return $credential !== null ? $this->principal($credential) : null;
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
     * @return array<string, mixed>
     */
    private function principal(DavCredential $credential): array
    {
        return [
            'uri' => 'principals/'.$credential->username,
            '{DAV:}displayname' => $credential->username,
        ];
    }
}
