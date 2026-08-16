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
use Sabre\DAV\Exception as SabreException;
use Sabre\DAV\Locks\Backend\File as LockBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\Sync\Plugin as SyncPlugin;
use Sabre\DAV\TemporaryFileFilterPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\Response as SabreResponse;
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
 * backends owner-scope on top of that. Both directions of the exchange are
 * bridged through Laravel's own Request/Response instead of Sabre's default
 * PHP-SAPI plumbing (see __invoke): Sabre's Sapi::getRequest() reads the raw
 * $_SERVER/php://input globals, which under Octane/FrankenPHP worker mode
 * either don't reflect the request being handled or (for the response half)
 * get discarded once the controller returns its own Response — so every DAV
 * client (Contacts, Calendar, a WebDAV mount) saw either the wrong request or
 * a status code with an empty body.
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

        // Build Sabre's request from Laravel's own (already-parsed, runtime-
        // normalized) Request rather than trusting Sapi::getRequest() to read
        // real $_SERVER/php://input — under Octane/FrankenPHP worker mode
        // those globals aren't guaranteed to describe the request currently
        // being handled. Symfony's HeaderBag already reconstructs a synthetic
        // Authorization header from PHP_AUTH_USER/PASS when needed, so
        // WebDavAuth's Basic-auth parsing keeps working unchanged.
        $davRequest = new SabreRequest($request->getMethod(), $request->getRequestUri(), $request->headers->all(), $request->getContent());
        $davRequest->setAbsoluteUrl($request->fullUrl());
        $server->httpRequest = $davRequest;
        $server->httpResponse = new SabreResponse;
        $server->httpRequest->setBaseUrl($server->getBaseUri());

        // Deliberately not $server->start(): on the success path that calls
        // invokeMethod(..., sendResponse: true), which sends the response by
        // calling header()/file_put_contents('php://output', ...) directly —
        // bypassing Laravel entirely. Under classic PHP-FPM those raw writes
        // happen to reach the client anyway, but under FrankenPHP's worker
        // mode they're captured and then discarded once the controller
        // returns its own (until now empty) Response, so every DAV client —
        // Contacts, Calendar, a WebDAV mount, all of them — saw a status code
        // with no body. Driving invokeMethod ourselves with sendResponse:
        // false keeps Sabre from touching the SAPI at all; we convert its
        // populated httpResponse into a real Response below instead.
        try {
            $server->invokeMethod($server->httpRequest, $server->httpResponse, false);
        } catch (\Throwable $e) {
            // invokeMethod(sendResponse: false) does not catch anything itself
            // (Server::start()'s own try/catch, skipped above, normally does) —
            // so protocol-level signals like "401, please authenticate" or "404,
            // no such collection" arrive here as thrown exceptions too, not just
            // genuine failures. Rebuild the same DAV-shaped error response
            // Server::start() would have sent, status/headers/body included
            // (e.g. WWW-Authenticate on a 401), instead of flattening every one
            // of these into a bare 500.
            self::renderSabreException($server, $e);
        }

        $laravelResponse = response($server->httpResponse->getBodyAsString(), $server->httpResponse->getStatus());
        foreach ($server->httpResponse->getHeaders() as $name => $values) {
            // Sabre's Message::getHeaders() is declared `array` with no
            // generic annotation, so PHPStan sees $values/$value as mixed
            // even though setHeader()/addHeader() only ever store string[]
            // (verified against sabre/http's own source) — guard rather than
            // blind-cast so a genuinely unexpected value is dropped, not
            // silently stringified into something wrong.
            foreach ((array) $values as $i => $value) {
                if (is_string($value)) {
                    $laravelResponse->headers->set($name, $value, $i === 0);
                }
            }
        }

        return $laravelResponse;
    }

    /**
     * Mirrors the exception branch of Sabre\DAV\Server::start() (which we no
     * longer call — see __invoke): turn a thrown exception into the same
     * DAV-shaped <d:error> response a real Sabre server would emit, with the
     * correct status code and headers for Sabre\DAV\Exception subclasses
     * (NotAuthenticated → 401 + WWW-Authenticate, NotFound → 404, ...).
     * Anything else is an actual bug, logged and reported as a plain 500.
     */
    private static function renderSabreException(Server $server, \Throwable $e): void
    {
        $isDavException = $e instanceof SabreException;
        if (! $isDavException) {
            report($e);
        }

        $status = $isDavException ? $e->getHTTPCode() : 500;
        $headers = $isDavException ? $e->getHTTPHeaders($server) : [];
        $headers['Content-Type'] = 'application/xml; charset=utf-8';

        $dom = new \DOMDocument('1.0', 'utf-8');
        $error = $dom->createElementNS('DAV:', 'd:error');
        // Some Sabre\DAV\Exception subclasses (e.g. PreconditionFailed) emit
        // an `s:`-prefixed element in their own serialize() without declaring
        // that namespace themselves — Server::start()'s own catch block
        // always declares it on the root first, unconditionally, so mirror
        // that here rather than only on the plain-500 fallback below.
        $error->setAttribute('xmlns:s', Server::NS_SABREDAV);
        $dom->appendChild($error);
        if ($isDavException) {
            $e->serialize($server, $error);
        } else {
            $error->appendChild($dom->createElement('s:message', 'Internal server error'));
        }

        $server->httpResponse->setStatus($status);
        $server->httpResponse->setHeaders($headers);
        // DOMDocument::saveXML() is declared string|false (a genuine
        // serialization failure); fall back to a minimal stub rather than
        // ever hand Sabre's Response a non-string body.
        $server->httpResponse->setBody($dom->saveXML() ?: '<d:error xmlns:d="DAV:"><d:message>Internal server error</d:message></d:error>');
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
