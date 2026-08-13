<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Calendar / CalDAV settings: show the sync URL + username and a downloadable
 * Apple configuration profile for iOS/macOS. Sync uses the ONE app-specific
 * WebDAV password (users.webdav_password) that also unlocks the Files drive and
 * CardDAV — set/cleared via WebDavAccessController — so this page only reflects
 * whether that password is set. The profile carries the username but never the
 * password (only its hash is stored). Mirrors Settings\ContactsController.
 */
class CalendarController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $this->requireUser($request);
        $davUrl = url('/dav/');

        return view('spa', [
            'davUrl' => $davUrl,
            'username' => $user->email,
            'hasPassword' => is_string($user->webdav_password) && $user->webdav_password !== '',
            'qr' => (new SvgWriter)->write(new QrCode($davUrl))->getDataUri(),
        ]);
    }

    /** Downloadable Apple configuration profile (CalDAV account) for iOS/macOS. */
    public function profile(Request $request): Response
    {
        $user = $this->requireUser($request);
        $plist = $this->mobileconfig($request->getHost(), (string) $user->email);

        return response($plist, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ledgerline-calendar.mobileconfig"',
        ]);
    }

    private function mobileconfig(string $host, string $username): string
    {
        $accountUuid = (string) Str::uuid();
        $profileUuid = (string) Str::uuid();
        $u = htmlspecialchars($username, ENT_XML1);
        $h = htmlspecialchars($host, ENT_XML1);

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
              <key>PayloadIdentifier</key><string>de.ledgerline.caldav.{$accountUuid}</string>
              <key>PayloadUUID</key><string>{$accountUuid}</string>
              <key>PayloadDisplayName</key><string>Ledgerline Calendar</string>
              <key>CalDAVAccountDescription</key><string>Ledgerline</string>
              <key>CalDAVHostName</key><string>{$h}</string>
              <key>CalDAVUsername</key><string>{$u}</string>
              <key>CalDAVUseSSL</key><true/>
              <key>CalDAVPort</key><integer>443</integer>
              <key>CalDAVPrincipalURL</key><string>/dav/</string>
            </dict>
          </array>
          <key>PayloadDisplayName</key><string>Ledgerline</string>
          <key>PayloadIdentifier</key><string>de.ledgerline.calendar</string>
          <key>PayloadType</key><string>Configuration</string>
          <key>PayloadUUID</key><string>{$profileUuid}</string>
          <key>PayloadVersion</key><integer>1</integer>
        </dict>
        </plist>
        XML;
    }
}
