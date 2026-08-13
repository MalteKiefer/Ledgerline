<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * API mirror of the web Settings/ContactsController@profile: a downloadable Apple
 * configuration profile for iOS/macOS. It provisions BOTH accounts against the one
 * unified /dav server — CardDAV (contacts) AND CalDAV (calendar) — so a single
 * install syncs contacts + calendars. The profile carries the username (the user's
 * email) but never a password — sync uses the app-specific webdav_password, only its
 * hash is stored. Gated by device auth + module:contacts (route level).
 */
class ContactsProfileController extends Controller
{
    /** Downloadable Apple configuration profile (CardDAV + CalDAV) for iOS/macOS. */
    public function carddavProfile(Request $request): Response
    {
        $user = $this->requireUser($request);
        $plist = $this->mobileconfig($request->getHost(), (string) $user->email, $request->isSecure());

        return response($plist, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ledgerline-sync.mobileconfig"',
        ]);
    }

    private function mobileconfig(string $host, string $username, bool $secure): string
    {
        $cardUuid = (string) Str::uuid();
        $calUuid = (string) Str::uuid();
        $profileUuid = (string) Str::uuid();
        $u = htmlspecialchars($username, ENT_XML1);
        $h = htmlspecialchars($host, ENT_XML1);
        // Apple defaults to :443 when UseSSL is on; a plain-HTTP LAN host needs the
        // real (non-TLS) port on the payload so autodiscovery hits the right place.
        $ssl = $secure ? '<true/>' : '<false/>';
        $port = $secure ? 443 : (int) (parse_url('//'.$host, PHP_URL_PORT) ?: 80);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
        <plist version="1.0">
        <dict>
          <key>PayloadContent</key>
          <array>
            <dict>
              <key>PayloadType</key><string>com.apple.carddav.account</string>
              <key>PayloadVersion</key><integer>1</integer>
              <key>PayloadIdentifier</key><string>de.ledgerline.carddav.{$cardUuid}</string>
              <key>PayloadUUID</key><string>{$cardUuid}</string>
              <key>PayloadDisplayName</key><string>Ledgerline Contacts</string>
              <key>CardDAVAccountDescription</key><string>Ledgerline</string>
              <key>CardDAVHostName</key><string>{$h}</string>
              <key>CardDAVUsername</key><string>{$u}</string>
              <key>CardDAVUseSSL</key>{$ssl}
              <key>CardDAVPort</key><integer>{$port}</integer>
              <key>CardDAVPrincipalURL</key><string>/dav/</string>
            </dict>
            <dict>
              <key>PayloadType</key><string>com.apple.caldav.account</string>
              <key>PayloadVersion</key><integer>1</integer>
              <key>PayloadIdentifier</key><string>de.ledgerline.caldav.{$calUuid}</string>
              <key>PayloadUUID</key><string>{$calUuid}</string>
              <key>PayloadDisplayName</key><string>Ledgerline Calendar</string>
              <key>CalDAVAccountDescription</key><string>Ledgerline</string>
              <key>CalDAVHostName</key><string>{$h}</string>
              <key>CalDAVUsername</key><string>{$u}</string>
              <key>CalDAVUseSSL</key>{$ssl}
              <key>CalDAVPort</key><integer>{$port}</integer>
              <key>CalDAVPrincipalURL</key><string>/dav/</string>
            </dict>
          </array>
          <key>PayloadDisplayName</key><string>Ledgerline Sync</string>
          <key>PayloadIdentifier</key><string>de.ledgerline.sync</string>
          <key>PayloadType</key><string>Configuration</string>
          <key>PayloadUUID</key><string>{$profileUuid}</string>
          <key>PayloadVersion</key><integer>1</integer>
        </dict>
        </plist>
        XML;
    }
}
