<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Services\Mail\MbsyncConfig;
use App\Services\Mail\MbsyncOutcome;
use App\Services\Mail\MbsyncRunner;
use App\Support\BinaryProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * MbsyncConfig::render() asserts the generated config's TEXT (never a live
 * IMAP run — the real mbsync binary is exercised only in the deploy image,
 * per the task brief). MbsyncRunner tests exercise the two paths that don't
 * need the binary at all: the egress guard short-circuit, and the
 * binary-absent degrade path.
 */
class MbsyncConfigTest extends TestCase
{
    use RefreshDatabase;

    private const STATE_DIR = '/tmp/ll-mbsync-test/state';

    private const MAILDIR_DIR = '/tmp/ll-mbsync-test/maildir';

    protected function setUp(): void
    {
        parent::setUp();
        // RefreshDatabase resets the DB (so account IDs restart low) but not
        // the filesystem — MbsyncRunner's scratch dirs are keyed by account
        // ID and would otherwise carry stale state / false-negative
        // "directory already exists" results across separate test runs.
        File::deleteDirectory(storage_path('app/mail-sync'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/mail-sync'));
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attrs */
    private function render(array $attrs = []): string
    {
        $account = MailAccount::factory()->make(array_merge([
            'id' => 42,
            'host' => 'imap.example.com',
            'port' => 993,
            'username' => 'archive@example.com',
            'password' => 'super-secret-imap-pw',
            'encryption' => 'ssl',
            'folders' => null,
        ], $attrs));

        return (new MbsyncConfig)->render($account, self::STATE_DIR, self::MAILDIR_DIR);
    }

    public function test_ssl_account_config_is_pull_only_and_never_writes_the_origin(): void
    {
        $config = $this->render();

        // Required directives, exactly as the brief specifies.
        $this->assertStringContainsString('Sync Pull', $config);
        $this->assertStringContainsString('Expunge None', $config);
        $this->assertStringContainsString('Create Near', $config);
        $this->assertStringContainsString('Remove None', $config);
        $this->assertStringContainsString('CopyArrivalDate yes', $config);
        $this->assertStringContainsString('Host "imap.example.com"', $config);
        $this->assertStringContainsString('Port 993', $config);
        $this->assertStringContainsString('SSLType IMAPS', $config);

        // Never any directive that could write to or delete from the origin.
        $this->assertStringNotContainsString('Sync Push', $config);
        $this->assertStringNotContainsString('Sync Both', $config);
        $this->assertStringNotContainsString('Expunge Both', $config);
        $this->assertStringNotContainsString('Expunge Far', $config);
        $this->assertStringNotContainsString('Create Both', $config);
        $this->assertStringNotContainsString('Create Far', $config);
        $this->assertStringNotContainsString('Remove Both', $config);
        $this->assertStringNotContainsString('Remove Far', $config);

        // The raw password is never written into the file.
        $this->assertStringNotContainsString('super-secret-imap-pw', $config);

        // A PassCmd fetches it instead, keyed only by the account's numeric id.
        $this->assertMatchesRegularExpression('/^PassCmd\s+"/m', $config);
        $this->assertStringContainsString('mail:account-password 42', $config);

        // Far = IMAP, Near = the given Maildir scratch path.
        $this->assertStringContainsString('IMAPStore', $config);
        $this->assertStringContainsString('MaildirStore', $config);
        $this->assertStringContainsString('Path "'.self::MAILDIR_DIR.'/"', $config);
        $this->assertStringContainsString('Inbox "'.self::MAILDIR_DIR.'/INBOX"', $config);

        // UID/UIDVALIDITY state persists on the (durable) scratch state dir.
        $this->assertStringContainsString('SyncState "'.self::STATE_DIR.'/"', $config);
    }

    public function test_starttls_account_gets_the_starttls_directive(): void
    {
        $config = $this->render(['encryption' => 'starttls', 'port' => 143]);

        $this->assertStringContainsString('SSLType STARTTLS', $config);
        $this->assertStringNotContainsString('SSLType IMAPS', $config);
        $this->assertStringNotContainsString('SSLType None', $config);
    }

    public function test_none_account_gets_no_tls(): void
    {
        $config = $this->render(['encryption' => 'none', 'port' => 143]);

        $this->assertStringContainsString('SSLType None', $config);
        $this->assertStringNotContainsString('SSLType IMAPS', $config);
        $this->assertStringNotContainsString('SSLType STARTTLS', $config);
    }

    public function test_tls_encryption_maps_to_implicit_imaps(): void
    {
        // mbsync exposes only IMAPS (implicit) and STARTTLS (opportunistic) as
        // secure transports; 'tls' is treated as implicit, same as 'ssl'.
        $config = $this->render(['encryption' => 'tls']);

        $this->assertStringContainsString('SSLType IMAPS', $config);
    }

    public function test_folders_restriction_limits_patterns_to_the_named_folders(): void
    {
        $config = $this->render(['folders' => ['INBOX', 'Sent Items']]);

        $this->assertStringContainsString('Patterns "INBOX" "Sent Items"', $config);
        $this->assertStringNotContainsString('Patterns *', $config);
    }

    public function test_no_folders_configured_syncs_everything(): void
    {
        $config = $this->render(['folders' => null]);

        $this->assertStringContainsString('Patterns *', $config);
    }

    public function test_runner_rejects_a_link_local_host_without_connecting(): void
    {
        $account = MailAccount::factory()->create([
            'host' => '169.254.169.254', // cloud-metadata / link-local
            'status' => 'idle',
            'last_error' => null,
        ]);

        $stateDir = storage_path('app/mail-sync/'.$account->id.'/state');
        $maildirDir = storage_path('app/mail-sync/'.$account->id.'/maildir');
        $this->assertDirectoryDoesNotExist($stateDir);
        $this->assertDirectoryDoesNotExist($maildirDir);

        $result = (new MbsyncRunner)->run($account);

        $this->assertSame(MbsyncOutcome::HostRejected, $result->outcome);
        $this->assertFalse($result->ok);

        // Rejected before any scratch prep or process was ever spawned.
        $this->assertDirectoryDoesNotExist($stateDir);
        $this->assertDirectoryDoesNotExist($maildirDir);

        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertStringContainsString('not an allowed outbound destination', (string) $account->last_error);
    }

    public function test_runner_reports_unavailable_and_cleans_up_the_temp_config_when_mbsync_is_missing(): void
    {
        if (BinaryProcess::available('mbsync')) {
            $this->markTestSkipped('mbsync is installed on this host; the unavailable-binary path cannot be exercised here.');
        }

        $account = MailAccount::factory()->create([
            'host' => 'imap.example.com',
            'status' => 'idle',
            'last_error' => null,
        ]);

        $before = glob(sys_get_temp_dir().'/mbsync-*.conf') ?: [];

        $result = (new MbsyncRunner)->run($account);

        $this->assertSame(MbsyncOutcome::Unavailable, $result->outcome);
        $this->assertFalse($result->ok);

        // A purely environmental gap — the account itself is not marked errored.
        $account->refresh();
        $this->assertSame('idle', $account->status);
        $this->assertNull($account->last_error);

        // The rendered config's transient temp file left nothing behind.
        $after = glob(sys_get_temp_dir().'/mbsync-*.conf') ?: [];
        $this->assertSame($before, $after);
    }
}
