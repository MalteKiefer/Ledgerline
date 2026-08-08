<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dav\AddressBookBackend;
use App\Dav\FilesHome;
use App\Dav\PrincipalBackend;
use App\Dav\WebDavAuth;
use Illuminate\Http\Request;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Locks\Backend\File as LockBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\Sync\Plugin as SyncPlugin;
use Sabre\DAV\TemporaryFileFilterPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified WebDAV / CardDAV endpoint (/dav). One Sabre server exposes three
 * collections — principals, addressbooks (CardDAV) and files (the WebDAV drive)
 * — all authenticated with the single app-specific HTTP Basic password
 * (users.webdav_password). One password unlocks Files and Contacts; macOS/iOS
 * discover CardDAV via /.well-known/carddav → /dav/. DAVACL rejects
 * unauthenticated access and confines each principal to its own resources; the
 * backends owner-scope on top of that. Sabre emits the response via the PHP
 * SAPI, so this action returns an already-sent empty response.
 */
class WebDavController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $lockDir = storage_path('app/dav-locks');
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $principals = new PrincipalBackend;

        $auth = new WebDavAuth;
        $auth->setRealm('Ledgerline DAV');

        $server = new Server([
            new PrincipalCollection($principals),
            new AddressBookRoot($principals, app(AddressBookBackend::class)),
            new FilesHome,
        ]);
        $server->setBaseUri('/dav/');

        $server->addPlugin(new AuthPlugin($auth));

        // Confine an authenticated principal to its own resources.
        $acl = new AclPlugin;
        $acl->allowUnauthenticatedAccess = false;
        $acl->principalCollectionSet = ['principals'];
        $server->addPlugin($acl);

        $server->addPlugin(new CardDAVPlugin);
        $server->addPlugin(new SyncPlugin);

        // macOS Finder requires DAV class-2 locking; back it with the local disk
        // (matches the pre-existing Files WebDAV behaviour). The temp-file filter
        // swallows Finder's probe files so mounting does not error.
        $server->addPlugin(new LocksPlugin(new LockBackend($lockDir)));
        $server->addPlugin(new TemporaryFileFilterPlugin(sys_get_temp_dir().'/ll-webdav'));

        $server->start();

        // Sabre has already written headers + body to the SAPI; hand Laravel an
        // empty, already-sent response so it does not emit anything further.
        return response('', $server->httpResponse->getStatus());
    }
}
