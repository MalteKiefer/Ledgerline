<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\ContactSyncSource;
use App\Support\OutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small RFC 6352 client used only by the queue worker. It never follows a
 * redirect without independently re-validating and pinning the next target.
 */
final class CardDavReplicaClient
{
    private const MAX_REDIRECTS = 3;
    private const MAX_XML_BYTES = 5_000_000;

    /** @return list<array{uri:string,etag:?string,vcard:string}> */
    public function cards(ContactSyncSource $source): array
    {
        if ($source->provider === 'google' && str_contains((string) $source->endpoint, '/.well-known/')) {
            $source->forceFill(['endpoint' => $this->discoverAddressBook($source)])->save();
        }
        $xml = '<?xml version="1.0" encoding="utf-8"?><c:addressbook-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:carddav"><d:prop><d:getetag/><c:address-data/></d:prop></c:addressbook-query>';
        $response = $this->request($source, 'REPORT', (string) $source->endpoint, $xml, ['Depth' => '1']);
        if ($response->status() !== 207) {
            throw new RuntimeException('CardDAV REPORT failed with HTTP '.$response->status().'.');
        }

        return $this->parseCards($response->body());
    }

    /** RFC 6764/6352 discovery used for Google; no CardDAV URL is hard-coded. */
    private function discoverAddressBook(ContactSyncSource $source): string
    {
        $principalRequest = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal/></d:prop></d:propfind>';
        $principalResponse = $this->request($source, 'PROPFIND', (string) $source->endpoint, $principalRequest, ['Depth' => '0']);
        if ($principalResponse->status() !== 207) {
            throw new RuntimeException('CardDAV principal discovery failed with HTTP '.$principalResponse->status().'.');
        }
        $principal = $this->propertyHref($principalResponse->body(), 'current-user-principal');
        if ($principal === null) {
            throw new RuntimeException('CardDAV did not return a current user principal.');
        }
        $homeRequest = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:carddav"><d:prop><c:addressbook-home-set/></d:prop></d:propfind>';
        $homeResponse = $this->request($source, 'PROPFIND', $this->resolveUri((string) $source->endpoint, $principal), $homeRequest, ['Depth' => '0']);
        if ($homeResponse->status() !== 207) {
            throw new RuntimeException('CardDAV address book discovery failed with HTTP '.$homeResponse->status().'.');
        }
        $home = $this->propertyHref($homeResponse->body(), 'addressbook-home-set');
        if ($home === null) {
            throw new RuntimeException('CardDAV did not return an address book home.');
        }
        $booksRequest = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:carddav"><d:prop><d:resourcetype/></d:prop></d:propfind>';
        $booksResponse = $this->request($source, 'PROPFIND', $this->resolveUri((string) $source->endpoint, $home), $booksRequest, ['Depth' => '1']);
        if ($booksResponse->status() !== 207) {
            throw new RuntimeException('CardDAV address book list failed with HTTP '.$booksResponse->status().'.');
        }
        $doc = $this->xml($booksResponse->body());
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('d', 'DAV:');
        $xp->registerNamespace('c', 'urn:ietf:params:xml:ns:carddav');
        foreach ($xp->query('//d:response') ?: [] as $response) {
            if (! $response instanceof \DOMNode) {
                continue;
            }
            if ($xp->evaluate('boolean(.//d:resourcetype/c:addressbook)', $response) !== true) {
                continue;
            }
            $value = $xp->evaluate('string(d:href)', $response);
            $href = is_string($value) ? trim($value) : '';
            if ($href !== '') {
                return $this->resolveUri((string) $source->endpoint, $href);
            }
        }
        throw new RuntimeException('CardDAV did not expose an address book collection.');
    }

    public function put(ContactSyncSource $source, string $uri, string $vcard, ?string $etag = null): string
    {
        $headers = ['Content-Type' => 'text/vcard; charset=utf-8'];
        if ($etag !== null && $etag !== '') {
            $headers['If-Match'] = $etag;
        } else {
            $headers['If-None-Match'] = '*';
        }
        $response = $this->request($source, 'PUT', $this->resolveUri((string) $source->endpoint, $uri), $vcard, $headers);
        if (! in_array($response->status(), [200, 201, 204], true)) {
            throw new RuntimeException('CardDAV PUT failed with HTTP '.$response->status().'.');
        }

        return (string) ($response->header('ETag') ?? '');
    }

    public function delete(ContactSyncSource $source, string $uri, ?string $etag): void
    {
        $headers = $etag !== null && $etag !== '' ? ['If-Match' => $etag] : [];
        $response = $this->request($source, 'DELETE', $this->resolveUri((string) $source->endpoint, $uri), null, $headers);
        if (! in_array($response->status(), [200, 202, 204, 404], true)) {
            throw new RuntimeException('CardDAV DELETE failed with HTTP '.$response->status().'.');
        }
    }

    /** Send one authenticated CardDAV request with bounded, SSRF-guarded redirects. */
    /** @param array<string, string> $headers */
    private function request(ContactSyncSource $source, string $method, string $url, ?string $body = null, array $headers = []): \Illuminate\Http\Client\Response
    {
        for ($attempt = 0; $attempt <= self::MAX_REDIRECTS; $attempt++) {
            if (! OutboundUrl::safe($url)) {
                throw new RuntimeException('CardDAV endpoint is not an allowed outbound URL.');
            }
            $client = $this->authenticatedClient($source, $url)->withHeaders(array_merge(['Accept' => 'application/xml, text/vcard'], $headers));
            if ($body !== null) {
                $client = $client->withBody($body, (string) ($headers['Content-Type'] ?? 'application/xml; charset=utf-8'));
            }
            $response = $client->send($method, $url);
            if (! in_array($response->status(), [301, 302, 307, 308], true)) {
                return $response;
            }
            $location = $response->header('Location');
            if (! is_string($location) || $location === '') {
                throw new RuntimeException('CardDAV returned a redirect without a location.');
            }
            $url = $this->resolveUri($url, $location);
        }

        throw new RuntimeException('CardDAV redirect limit reached.');
    }

    private function authenticatedClient(ContactSyncSource $source, string $url): PendingRequest
    {
        $client = OutboundUrl::client($url, 30);
        if ($source->auth_type === 'basic') {
            return $client->withBasicAuth((string) $source->username, (string) $source->password);
        }
        if ($source->auth_type === 'oauth2') {
            $this->refreshGoogleToken($source);
        }
        $token = (string) $source->access_token;
        if ($token === '') {
            throw new RuntimeException('The CardDAV access token is missing.');
        }

        return $client->withToken($token);
    }

    /** Refresh only Google's OAuth grant; other Bearer providers can save a token directly. */
    private function refreshGoogleToken(ContactSyncSource $source): void
    {
        if ($source->access_token !== null && ($source->access_token_expires_at === null || $source->access_token_expires_at->isAfter(now()->addMinutes(2)))) {
            return;
        }
        if ($source->refresh_token === null || $source->oauth_client_id === null || $source->oauth_client_secret === null) {
            throw new RuntimeException('Google authorization needs to be reconnected.');
        }
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $source->oauth_client_id, 'client_secret' => $source->oauth_client_secret,
            'refresh_token' => $source->refresh_token, 'grant_type' => 'refresh_token',
        ]);
        $accessToken = $response->json('access_token');
        if (! $response->successful() || ! is_string($accessToken)) {
            throw new RuntimeException('Google token refresh failed.');
        }
        $expiresIn = $response->json('expires_in');
        $source->forceFill([
            'access_token' => $accessToken,
            'access_token_expires_at' => now()->addSeconds(is_numeric($expiresIn) ? max(60, (int) $expiresIn) : 3600),
        ])->save();
    }

    /** @return list<array{uri:string,etag:?string,vcard:string}> */
    private function parseCards(string $xml): array
    {
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw new RuntimeException('CardDAV response exceeded the safe size limit.');
        }
        $doc = $this->xml($xml);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('d', 'DAV:');
        $xp->registerNamespace('c', 'urn:ietf:params:xml:ns:carddav');
        $out = [];
        foreach ($xp->query('//d:response') ?: [] as $response) {
            if (! $response instanceof \DOMNode) {
                continue;
            }
            $uriValue = $xp->evaluate('string(d:href)', $response);
            $vcardValue = $xp->evaluate('string(.//c:address-data)', $response);
            $uri = is_string($uriValue) ? trim($uriValue) : '';
            $vcard = is_string($vcardValue) ? $vcardValue : '';
            if ($uri === '' || $vcard === '' || strlen($vcard) > self::MAX_XML_BYTES) {
                continue;
            }
            $etagValue = $xp->evaluate('string(.//d:getetag)', $response);
            $etag = is_string($etagValue) ? trim($etagValue) : '';
            $out[] = ['uri' => $uri, 'etag' => $etag !== '' ? $etag : null, 'vcard' => $vcard];
        }

        return $out;
    }

    private function propertyHref(string $xml, string $property): ?string
    {
        $doc = $this->xml($xml);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('d', 'DAV:');
        $value = $xp->evaluate('string(//*[local-name()='.$this->xpathLiteral($property).']//d:href)');
        $href = is_string($value) ? trim($value) : '';

        return $href !== '' ? $href : null;
    }

    private function xml(string $xml): \DOMDocument
    {
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw new RuntimeException('CardDAV response exceeded the safe size limit.');
        }
        $doc = new \DOMDocument;
        if (@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT) !== true) {
            throw new RuntimeException('CardDAV returned invalid XML.');
        }

        return $doc;
    }

    private function xpathLiteral(string $value): string
    {
        return "'".str_replace("'", "',\"'\",'", $value)."'";
    }

    private function resolveUri(string $base, string $uri): string
    {
        if (preg_match('#^https?://#i', $uri) === 1) {
            return $uri;
        }
        $parts = parse_url($base);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('CardDAV endpoint is invalid.');
        }
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($uri, '/')) {
            return $origin.$uri;
        }
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/').'/' : '/';

        return $origin.$path.$uri;
    }
}
