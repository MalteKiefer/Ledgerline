<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dav\AddressBookBackend;
use App\Dav\AuthBackend;
use App\Dav\PrincipalBackend;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\Sync\Plugin as SyncPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Mounts the sabre/dav CardDAV server at /dav. Registered outside the web
 * middleware group (no session/CSRF); sabre does its own Basic auth and writes
 * the response directly, so the handler ends the request.
 */
class DavController extends Controller
{
    public function handle(AuthBackend $auth, PrincipalBackend $principals, AddressBookBackend $cards): void
    {
        $server = new Server([
            new PrincipalCollection($principals),
            new AddressBookRoot($principals, $cards),
        ]);
        $server->setBaseUri('/dav/');

        $server->addPlugin(new AuthPlugin($auth));
        $server->addPlugin(new CardDAVPlugin);
        $server->addPlugin(new SyncPlugin);

        $server->start();
        exit;
    }
}
