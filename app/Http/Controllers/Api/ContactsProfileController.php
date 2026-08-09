<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * API mirror of the web Settings/ContactsController@profile: a downloadable Apple
 * configuration profile (CardDAV account) for iOS/macOS. The web controller returns
 * a Blade page for its settings screen; only the downloadable .mobileconfig artifact
 * needs an API twin. The profile carries the username (the user's email) but never a
 * password — sync uses the app-specific webdav_password, only its hash is stored.
 * Gated by device auth + module:contacts (route level).
 */
class ContactsProfileController extends Controller
{
    /** Downloadable Apple configuration profile (CardDAV account) for iOS/macOS. */
    public function carddavProfile(Request $request): Response
    {
        $user = $this->requireUser($request);
        $plist = $this->mobileconfig($request->getHost(), (string) $user->email);

        return response($plist, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ledgerline-contacts.mobileconfig"',
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
              <key>PayloadType</key><string>com.apple.carddav.account</string>
              <key>PayloadVersion</key><integer>1</integer>
              <key>PayloadIdentifier</key><string>de.ledgerline.carddav.{$accountUuid}</string>
              <key>PayloadUUID</key><string>{$accountUuid}</string>
              <key>PayloadDisplayName</key><string>Ledgerline Contacts</string>
              <key>CardDAVAccountDescription</key><string>Ledgerline</string>
              <key>CardDAVHostName</key><string>{$h}</string>
              <key>CardDAVUsername</key><string>{$u}</string>
              <key>CardDAVUseSSL</key><true/>
              <key>CardDAVPort</key><integer>443</integer>
              <key>CardDAVPrincipalURL</key><string>/dav/</string>
            </dict>
          </array>
          <key>PayloadDisplayName</key><string>Ledgerline</string>
          <key>PayloadIdentifier</key><string>de.ledgerline.contacts</string>
          <key>PayloadType</key><string>Configuration</string>
          <key>PayloadUUID</key><string>{$profileUuid}</string>
          <key>PayloadVersion</key><integer>1</integer>
        </dict>
        </plist>
        XML;
    }
}
