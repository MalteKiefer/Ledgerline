<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use App\Support\OutboundUrl;
use Throwable;

/**
 * Safe, best-effort mail-server discovery for the account setup wizard.
 *
 * It only queries DNS and HTTPS endpoints below the supplied email domain;
 * credentials are never accepted, stored or sent. Every discovered endpoint
 * is rechecked with OutboundUrl before it can be returned to the client.
 */
class MailAutoconfig
{
    /** @return array<string, mixed> */
    public function discover(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $domain = (string) strrchr($email, '@');
        $domain = ltrim($domain, '@');

        $domainIps = OutboundUrl::resolveHost($domain);
        $imap = null;
        $smtp = null;
        $sources = [];

        foreach ($this->autoconfigUrls($domain, $email) as $url) {
            $config = $this->fetchAutoconfig($url);
            if ($config === null) {
                continue;
            }
            $imap ??= $config['imap'];
            $smtp ??= $config['smtp'];
            $sources[] = 'autoconfig';
            if ($imap !== null && $smtp !== null) {
                break;
            }
        }

        $imap ??= $this->fromSrv('_imaps._tcp.'.$domain, 'ssl');
        $imap ??= $this->fromSrv('_imap._tcp.'.$domain, 'starttls');
        $smtp ??= $this->fromSrv('_submissions._tcp.'.$domain, 'ssl');
        $smtp ??= $this->fromSrv('_submission._tcp.'.$domain, 'starttls');
        if ($imap !== null || $smtp !== null) {
            $sources[] = 'srv';
        }

        // A DNS-verified conventional hostname is a convenience fallback, not
        // a connection test. The wizard labels it accordingly and lets users
        // review/edit it before credentials are entered.
        $imap ??= $this->firstResolvable(['imap.'.$domain, 'mail.'.$domain], 993, 'ssl');
        $smtp ??= $this->firstResolvable(['smtp.'.$domain, 'mail.'.$domain], 587, 'starttls');
        if ($imap !== null || $smtp !== null) {
            $sources[] = 'dns';
        }

        return [
            'email' => $email,
            'domain' => $domain,
            'domain_resolves' => $domainIps !== [],
            'imap' => $imap,
            'smtp' => $smtp,
            'sources' => array_values(array_unique($sources)),
            // Outlook/Exchange uses this standard SRV label. It is reported so
            // users know why IMAP settings were not inferred from an Exchange-
            // only Autodiscover record.
            'outlook_autodiscover' => $this->srvRecords('_autodiscover._tcp.'.$domain) !== [],
        ];
    }

    /** @return list<string> */
    protected function autoconfigUrls(string $domain, string $email): array
    {
        $query = http_build_query(['emailaddress' => $email], encoding_type: PHP_QUERY_RFC3986);

        return [
            'https://autoconfig.'.$domain.'/mail/config-v1.1.xml?'.$query,
            'https://'.$domain.'/.well-known/autoconfig/mail/config-v1.1.xml?'.$query,
        ];
    }

    /** @return array{imap: ?array{host:string,port:int,encryption:string,username:?string},smtp: ?array{host:string,port:int,encryption:string,username:?string}}|null */
    protected function fetchAutoconfig(string $url): ?array
    {
        try {
            $response = OutboundUrl::client($url, 4)
                ->accept('application/xml, text/xml')
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || strlen($response->body()) > 131_072) {
            return null;
        }

        return $this->parseAutoconfig($response->body());
    }

    /** @return array{imap: ?array{host:string,port:int,encryption:string,username:?string},smtp: ?array{host:string,port:int,encryption:string,username:?string}}|null */
    protected function parseAutoconfig(string $xml): ?array
    {
        if (str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if ($root === false) {
                return null;
            }
            $imap = null;
            $smtp = null;
            foreach ($root->xpath('//*[local-name()="incomingServer" or local-name()="outgoingServer"]') ?: [] as $server) {
                $type = strtolower((string) ($server['type'] ?? ''));
                $candidate = $this->xmlCandidate($server, $type === 'smtp' ? 'starttls' : 'ssl');
                if ($candidate === null) {
                    continue;
                }
                if ($type === 'imap' && $imap === null) {
                    $imap = $candidate;
                }
                if ($type === 'smtp' && $smtp === null) {
                    $smtp = $candidate;
                }
            }

            return $imap === null && $smtp === null ? null : compact('imap', 'smtp');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array{host:string,port:int,encryption:string,username:?string}|null */
    private function xmlCandidate(\SimpleXMLElement $server, string $fallbackEncryption): ?array
    {
        $host = trim((string) ($server->hostname ?? ''));
        $port = (int) ($server->port ?? 0);
        $socketType = strtolower(trim((string) ($server->socketType ?? '')));
        $encryption = match ($socketType) {
            'ssl', 'ssl/tls' => 'ssl',
            'starttls' => 'starttls',
            'plain' => 'none',
            default => $fallbackEncryption,
        };

        return $this->candidate($host, $port, $encryption, trim((string) ($server->username ?? '')) ?: null);
    }

    /** @return array{host:string,port:int,encryption:string,username:?string}|null */
    protected function fromSrv(string $name, string $encryption): ?array
    {
        foreach ($this->srvRecords($name) as $record) {
            $candidate = $this->candidate($record['host'], $record['port'], $encryption);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<array{host:string,port:int}> */
    protected function srvRecords(string $name): array
    {
        $out = BinaryProcess::run(['dig', '+short', 'SRV', $name], 3);
        if ($out === null) {
            return [];
        }

        $records = [];
        foreach (preg_split('/\R/', trim($out)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) !== 4 || ! ctype_digit($parts[2])) {
                continue;
            }
            $host = rtrim($parts[3], '.');
            $port = (int) $parts[2];
            if ($host !== '' && $port > 0) {
                $records[] = ['host' => $host, 'port' => $port];
            }
        }

        return $records;
    }

    /** @param list<string> $hosts
     * @return array{host:string,port:int,encryption:string,username:?string}|null */
    private function firstResolvable(array $hosts, int $port, string $encryption): ?array
    {
        foreach ($hosts as $host) {
            $candidate = $this->candidate($host, $port, $encryption);
            if ($candidate !== null && OutboundUrl::resolveHost($host) !== []) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{host:string,port:int,encryption:string,username:?string}|null */
    private function candidate(string $host, int $port, string $encryption, ?string $username = null): ?array
    {
        $host = mb_strtolower(trim($host));
        if ($host === '' || ! OutboundUrl::mailPortAllowed($port) || ! OutboundUrl::hostAllowed($host)) {
            return null;
        }

        return compact('host', 'port', 'encryption', 'username');
    }
}
