<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Contacts\DavCredentialService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Contacts / CardDAV settings: enable the feature (generates the DAV login),
 * show the sync URL + username, a QR code of the URL, and a downloadable Apple
 * configuration profile for iOS/macOS. The password is shown once and only its
 * hash is stored, so the profile carries the username but not the password.
 */
class ContactsController extends Controller
{
    public function edit(DavCredentialService $credentials): View
    {
        $credential = $credentials->for(auth()->id());
        $davUrl = url('/dav/');

        return view('settings.contacts.edit', [
            'credential' => $credential,
            'davUrl' => $davUrl,
            'qr' => $credential !== null ? $this->qr($davUrl) : null,
        ]);
    }

    public function generate(Request $request, DavCredentialService $credentials): RedirectResponse
    {
        $result = $credentials->generate($request->user()->id);

        return redirect()->route('settings.contacts.edit')
            ->with('status', __('flash.dav_password_generated'))
            ->with('dav_username', $result['credential']->username)
            ->with('dav_password', $result['password']);
    }

    /** Downloadable Apple configuration profile (CardDAV account) for iOS/macOS. */
    public function profile(Request $request, DavCredentialService $credentials): Response
    {
        $credential = $credentials->for($request->user()->id);
        abort_if($credential === null, 404);

        $host = $request->getHost();
        $plist = $this->mobileconfig($host, $credential->username);

        return response($plist, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ledgerline-contacts.mobileconfig"',
        ]);
    }

    private function qr(string $text): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($text);
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
          <key>PayloadDisplayName</key><string>Ledgerline Contacts</string>
          <key>PayloadIdentifier</key><string>de.ledgerline.contacts</string>
          <key>PayloadType</key><string>Configuration</string>
          <key>PayloadUUID</key><string>{$profileUuid}</string>
          <key>PayloadVersion</key><integer>1</integer>
        </dict>
        </plist>
        XML;
    }
}
