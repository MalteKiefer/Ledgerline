<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * API mirror of the web Settings/CalendarController@profile: a downloadable Apple
 * configuration profile that provisions a CalDAV account against the one unified
 * /dav server. Standalone (CalDAV only) so it works for a user who has the calendar
 * module but not contacts; the combined CardDAV+CalDAV profile lives on the contacts
 * route. Carries the username (email) but never a password — sync uses the
 * app-specific webdav_password (only its hash is stored). Gated by device auth +
 * module:calendar at the route level.
 */
class CalendarProfileController extends Controller
{
    /** Downloadable Apple CalDAV configuration profile for iOS/macOS. */
    public function caldavProfile(Request $request): Response
    {
        $user = $this->requireUser($request);
        $plist = $this->mobileconfig($request->getHost(), (string) $user->email, $request->isSecure());

        return response($plist, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ledgerline-calendar.mobileconfig"',
        ]);
    }

    private function mobileconfig(string $host, string $username, bool $secure): string
    {
        $calUuid = (string) Str::uuid();
        $profileUuid = (string) Str::uuid();
        $u = htmlspecialchars($username, ENT_XML1);
        $h = htmlspecialchars($host, ENT_XML1);
        // Derive SSL + port from the request instead of hardcoding 443: a plain-HTTP
        // LAN host needs UseSSL=false and its real port for autodiscovery to work.
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
          <key>PayloadDisplayName</key><string>Ledgerline Calendar</string>
          <key>PayloadIdentifier</key><string>de.ledgerline.calendar</string>
          <key>PayloadType</key><string>Configuration</string>
          <key>PayloadUUID</key><string>{$profileUuid}</string>
          <key>PayloadVersion</key><integer>1</integer>
        </dict>
        </plist>
        XML;
    }
}
