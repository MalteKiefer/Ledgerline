<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dav\FolderNode;
use App\Dav\WebDavAuth;
use Illuminate\Http\Request;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Locks\Backend\File as LockBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Server;
use Symfony\Component\HttpFoundation\Response;

/**
 * WebDAV endpoint (/dav) — mounts a user's Files tree as a network drive.
 * Authenticates via HTTP Basic (e-mail + app-specific WebDAV password); Sabre
 * handles the protocol and emits the response directly via the PHP SAPI, so this
 * action terminates the request itself.
 */
class WebDavController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $lockDir = storage_path('app/dav-locks');
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $auth = new WebDavAuth;
        $auth->setRealm('Ledgerline WebDAV');
        $server = new Server(new FolderNode(null));
        $server->setBaseUri('/dav/');
        $server->addPlugin(new AuthPlugin($auth));
        $server->addPlugin(new LocksPlugin(new LockBackend($lockDir)));
        $server->start();

        // Sabre has already written headers + body to the SAPI; hand Laravel an
        // empty, already-sent response so it does not emit anything further.
        return response('', $server->httpResponse->getStatus());
    }
}
