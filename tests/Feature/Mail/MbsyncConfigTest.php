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
use PHPUnit\Framework\Attributes\DataProvider;
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
        // isync >= 1.5.0 directive names (renamed from SSLType/SSLVersions).
        // `+1.2 +1.3` is the additive version syntax 1.5.x requires; the old
        // `SSLType`/`SSLVersions TLSv1.2 TLSv1.3` form is deprecated/rejected.
        $this->assertStringContainsString('TLSType IMAPS', $config);
        $this->assertStringContainsString('TLSVersions +1.2 +1.3', $config);
        $this->assertStringNotContainsString('SSLType', $config);
        $this->assertStringNotContainsString('SSLVersions', $config);

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

        $this->assertStringContainsString('TLSType STARTTLS', $config);
        $this->assertStringNotContainsString('TLSType IMAPS', $config);
        $this->assertStringNotContainsString('TLSType None', $config);
    }

    public function test_none_account_gets_no_tls(): void
    {
        $config = $this->render(['encryption' => 'none', 'port' => 143]);

        $this->assertStringContainsString('TLSType None', $config);
        $this->assertStringNotContainsString('TLSType IMAPS', $config);
        $this->assertStringNotContainsString('TLSType STARTTLS', $config);
    }

    public function test_tls_encryption_maps_to_implicit_imaps(): void
    {
        // mbsync exposes only IMAPS (implicit) and STARTTLS (opportunistic) as
        // secure transports; 'tls' is treated as implicit, same as 'ssl'.
        $config = $this->render(['encryption' => 'tls']);

        $this->assertStringContainsString('TLSType IMAPS', $config);
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

    /**
     * A folder name is an IMAP GLOB pattern, not a literal — quoting handles
     * spaces/`"`/`\` but does NOT disable `*`/`%`/`!` operator semantics, and
     * isync has no escape for a literal one. Assert the ACTUAL behavior (the
     * name is emitted verbatim inside its quotes, glob operator intact) rather
     * than a false "matched literally" guarantee. This is a benign matching
     * quirk — the read-only-origin guarantee holds regardless of what matches.
     */
    public function test_folder_name_with_a_glob_char_is_emitted_as_a_glob_not_a_literal(): void
    {
        $config = $this->render(['folders' => ['Archive*']]);

        // The `*` survives verbatim into the Patterns line (an IMAP glob),
        // NOT escaped into a literal — there is no escape to assert otherwise.
        $this->assertStringContainsString('Patterns "Archive*"', $config);
    }

    /**
     * CRITICAL: the config is line-oriented (`implode("\n", …)`), so a value
     * with an embedded newline could break out of its quoted string and inject
     * arbitrary physical config lines — a PoC injected `Sync Both` /
     * `Expunge Both` / `Create Both`, flipping the mirror to WRITE/DELETE the
     * origin. render() must FAIL CLOSED on any control character in any
     * interpolated account value (host / username / folders), before a config
     * can exist.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function controlCharInjectionProvider(): iterable
    {
        $inject = "\nSync Both\nExpunge Both\nCreate Both";

        yield 'host with newline injection' => ['host', 'imap.evil.test'.$inject];
        yield 'host with carriage return' => ['host', "imap.evil.test\rInbox"];
        yield 'host with NUL' => ['host', "imap.evil.test\0"];
        yield 'username with newline injection' => ['username', 'user@x.test'.$inject];
        yield 'username with carriage return' => ['username', "user@x.test\rx"];
        yield 'folder entry with newline injection' => ['folders', ['INBOX'.$inject]];
        yield 'folder entry with NUL' => ['folders', ["INBOX\0"]];
        yield 'folder entry with carriage return' => ['folders', ["INBOX\rSent"]];
    }

    /**
     * @param  mixed  $value
     */
    #[DataProvider('controlCharInjectionProvider')]
    public function test_render_rejects_control_characters_in_account_values(string $field, $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->render([$field => $value]);
    }

    /**
     * Belt-and-braces: even if the exception contract ever changed, the output
     * of a newline-laden value must NEVER contain an origin-writing directive.
     * render() throwing satisfies this (no output at all); this test asserts
     * the property directly.
     */
    public function test_a_newline_injection_can_never_produce_an_origin_writing_directive(): void
    {
        $config = null;

        try {
            $config = $this->render([
                'host' => "imap.evil.test\nSync Both\nExpunge Both\nCreate Both\nRemove Both\nCreate Far",
            ]);
        } catch (\InvalidArgumentException) {
            // Expected: fail-closed, no config produced.
        }

        if ($config !== null) {
            $this->assertStringNotContainsString('Sync Both', $config);
            $this->assertStringNotContainsString('Expunge Both', $config);
            $this->assertStringNotContainsString('Create Both', $config);
            $this->assertStringNotContainsString('Create Far', $config);
            $this->assertStringNotContainsString('Remove Both', $config);
        }

        // The only correct behavior in this codebase is to throw; assert it.
        $this->assertNull($config, 'render() must fail closed on a control character.');
    }

    public function test_render_throws_on_an_unsupported_encryption(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->render(['encryption' => 'bogus']);
    }

    public function test_runner_degrades_an_unsupported_encryption_to_a_failed_result(): void
    {
        // The unknown-encryption throw from render() must not escape run() —
        // it degrades to a Failed MbsyncResult with a redacted last_error.
        $account = MailAccount::factory()->create([
            'host' => 'imap.example.com',
            'encryption' => 'ssl',
            'status' => 'idle',
            'last_error' => null,
        ]);
        // Force an unsupported value past the model without re-validating.
        $account->forceFill(['encryption' => 'bogus']);

        $result = (new MbsyncRunner)->run($account);

        $this->assertSame(MbsyncOutcome::Failed, $result->outcome);
        $this->assertFalse($result->ok);

        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertNotNull($account->last_error);
        $this->assertStringNotContainsString('bogus', (string) $account->last_error);
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
