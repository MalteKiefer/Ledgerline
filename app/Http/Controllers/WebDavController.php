<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dav\AddressBookBackend;
use App\Dav\CalendarBackend;
use App\Dav\FilesHome;
use App\Dav\PrincipalBackend;
use App\Dav\WebDavAuth;
use Illuminate\Http\Request;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
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
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified WebDAV / CardDAV / CalDAV endpoint (/dav). One Sabre server exposes
 * four collections — principals, addressbooks (CardDAV), calendars (CalDAV) and
 * files (the WebDAV drive) — all authenticated with the single app-specific HTTP
 * Basic password (users.webdav_password). One password unlocks Files, Contacts
 * and Calendar; macOS/iOS discover CardDAV/CalDAV via /.well-known/carddav and
 * /.well-known/caldav → /dav/. DAVACL rejects
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
            new CalendarRoot($principals, app(CalendarBackend::class)),
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
        $server->addPlugin(new CalDAVPlugin);
        $server->addPlugin(new SyncPlugin);

        // macOS Finder requires DAV class-2 locking; back it with the local disk
        // (matches the pre-existing Files WebDAV behaviour). The temp-file filter
        // swallows Finder's probe files so mounting does not error.
        $server->addPlugin(new LocksPlugin(new LockBackend($lockDir)));
        $server->addPlugin(new TemporaryFileFilterPlugin(sys_get_temp_dir().'/ll-webdav'));

        // Harden GET responses. Sabre writes straight to the PHP SAPI, bypassing
        // the SecurityHeaders middleware, so a stored file (whose Content-Type is
        // client-influenced at upload) could otherwise be served inline and run
        // as same-origin script (stored XSS) on this internet-facing host. Emit
        // nosniff + an empty-sandbox CSP on every GET, and force
        // download + a neutral Content-Type for browser-executable types.
        $server->on('afterMethod:GET', function (RequestInterface $request, ResponseInterface $response): void {
            self::hardenGetResponse($response);
        });

        $server->start();

        // Sabre has already written headers + body to the SAPI; hand Laravel an
        // empty, already-sent response so it does not emit anything further.
        return response('', $server->httpResponse->getStatus());
    }

    /**
     * Harden a Sabre GET response: nosniff + an empty-sandbox CSP on every GET,
     * plus a forced download + neutral Content-Type for browser-executable
     * media types (a stored file's Content-Type is client-influenced at upload).
     * Extracted so the header policy is unit-testable without a real DAV socket.
     */
    public static function hardenGetResponse(ResponseInterface $response): void
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        $contentType = strtolower((string) $response->getHeader('Content-Type'));
        $base = trim(explode(';', $contentType, 2)[0]);
        $risky = [
            'text/html', 'application/xhtml+xml', 'image/svg+xml',
            'application/xml', 'text/xml',
            'application/javascript', 'text/javascript', 'application/x-javascript',
        ];
        if (in_array($base, $risky, true)) {
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Disposition', 'attachment');
        }
    }
}
