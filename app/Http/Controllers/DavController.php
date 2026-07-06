<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dav\AddressBookBackend;
use App\Dav\AuthBackend;
use App\Dav\CalDavBackend;
use App\Dav\Files\FileDavBackend;
use App\Dav\Files\FilesRoot;
use App\Dav\PrincipalBackend;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\Sync\Plugin as SyncPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Mounts the sabre/dav CardDAV server at /dav. Registered outside the web
 * middleware group (no session/CSRF); sabre does its own Basic auth and writes
 * the response directly, so the handler ends the request.
 */
class DavController extends Controller
{
    public function handle(AuthBackend $auth, PrincipalBackend $principals, AddressBookBackend $cards, CalDavBackend $calendars, FileDavBackend $files): void
    {
        $server = new Server([
            new PrincipalCollection($principals),
            new AddressBookRoot($principals, $cards),
            new CalendarRoot($principals, $calendars),
            new FilesRoot($principals, $files),
        ]);
        $server->setBaseUri('/dav/');

        $server->addPlugin(new AuthPlugin($auth));

        // Enforce that an authenticated principal can only reach its own
        // resources (no cross-principal/address-book access).
        $acl = new AclPlugin;
        $acl->allowUnauthenticatedAccess = false;
        $acl->principalCollectionSet = ['principals'];
        $server->addPlugin($acl);

        $server->addPlugin(new CardDAVPlugin);
        $server->addPlugin(new CalDAVPlugin);
        $server->addPlugin(new SyncPlugin);

        $server->start();
        exit;
    }
}
