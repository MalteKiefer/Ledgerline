<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MailAccount;
use App\Services\Mail\MbsyncConfig;
use InvalidArgumentException;
use Tests\TestCase;

class MbsyncConfigTest extends TestCase
{
    private function account(array $attrs = []): MailAccount
    {
        $a = new MailAccount;
        $a->forceFill(array_merge([
            'id' => 7,
            'host' => 'imap.example.com',
            'port' => 993,
            'username' => 'me@example.com',
            'encryption' => 'ssl',
            'folders' => null,
        ], $attrs));

        return $a;
    }

    public function test_renders_pull_only_read_only_origin_directives(): void
    {
        $cfg = (new MbsyncConfig)->render($this->account(), '/state/', '/maildir');

        $this->assertStringContainsString('Sync Pull', $cfg);
        $this->assertStringContainsString('Create Near', $cfg);
        $this->assertStringContainsString('Expunge None', $cfg);
        $this->assertStringContainsString('Remove None', $cfg);
        $this->assertStringContainsString('CopyArrivalDate yes', $cfg);
        $this->assertStringContainsString('TLSType IMAPS', $cfg);
        // Never a write direction to the origin.
        $this->assertStringNotContainsString('Sync Both', $cfg);
        $this->assertStringNotContainsString('Sync Push', $cfg);
        $this->assertStringNotContainsString('Expunge Both', $cfg);
    }

    public function test_password_is_never_written_into_the_config(): void
    {
        $account = $this->account();
        $account->forceFill(['password' => 'super-secret']);
        $cfg = (new MbsyncConfig)->render($account, '/state/', '/maildir');

        $this->assertStringNotContainsString('super-secret', $cfg);
        $this->assertStringContainsString('PassCmd', $cfg);
        $this->assertStringContainsString('mail:account-password 7', $cfg);
    }

    public function test_control_character_injection_fails_closed(): void
    {
        // A newline in the host would break out of the quoted string and inject
        // a physical config line (a PoC flipped the mirror to Sync Both).
        $account = $this->account(['host' => "imap.example.com\nSync Both"]);

        $this->expectException(InvalidArgumentException::class);
        (new MbsyncConfig)->render($account, '/state/', '/maildir');
    }

    public function test_unknown_encryption_fails_closed_no_plaintext_downgrade(): void
    {
        $account = $this->account(['encryption' => 'bogus']);

        $this->expectException(InvalidArgumentException::class);
        (new MbsyncConfig)->render($account, '/state/', '/maildir');
    }

    public function test_starttls_maps_to_starttls(): void
    {
        $cfg = (new MbsyncConfig)->render($this->account(['encryption' => 'starttls']), '/s/', '/m');
        $this->assertStringContainsString('TLSType STARTTLS', $cfg);
    }
}
