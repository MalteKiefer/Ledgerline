<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Servers\BackupInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * How a host is backed up.
 *
 * The properties worth holding: a hand-written cron script counts as a backup
 * (on real machines it usually is the backup), the log's age is what says a job
 * ran rather than the schedule that says it should have, an installed tool is
 * inventory and not protection, and nothing printed here carries a password.
 */
class ServerBackupInspectorTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Server
    {
        return Server::forceCreate([
            'user_id' => User::factory()->create()->id,
            'name' => 'Web',
            'host' => 'web.example',
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'x'],
            'host_key' => 'ssh-ed25519 AAAA test',
            'host_fingerprint' => 'SHA256:test',
            'enabled' => true,
        ]);
    }

    /**
     * @param  list<array{ok:bool,out:string}>  $queue
     * @return array{ok:bool,tools:list<array<string,string|null>>,agents:list<array<string,mixed>>,schedules:list<array<string,mixed>>,activities:list<array<string,string|null>>,repositories:list<string>,error:string|null}
     */
    private function inspect(array $queue): array
    {
        return (new BackupInspector(new VpnRecordingProbe($queue)))->inspect($this->server());
    }

    #[Test]
    public function a_hand_written_cron_script_counts_as_a_backup(): void
    {
        $out = "##LL:tools\nrsync\nborg\n"
            ."##LL:versions\nborg\tborg 1.2.4\n"
            ."##LL:cron\nroot|30 3 * * * /usr/bin/bash /opt/backup.sh >> /var/log/homeserver_backup.log 2>&1\n"
            ."##LL:end\n";
        // The second round trip asks how old that log is.
        $stat = '/var/log/homeserver_backup.log|'.(time() - 3600)."|20480\n";

        $result = $this->inspect([['ok' => true, 'out' => $out], ['ok' => true, 'out' => $stat]]);

        $this->assertCount(1, $result['schedules']);
        $job = $result['schedules'][0];
        $this->assertSame('cron', $job['kind']);
        $this->assertSame('30 3 * * *', $job['schedule']);
        $this->assertStringContainsString('/opt/backup.sh', $job['runs']);
        $this->assertSame('/var/log/homeserver_backup.log', $job['log']);
        // Evidence, not intent: this is what says it actually ran.
        $this->assertNotNull($job['last_run']);
        $this->assertSame(20480, $job['log_size']);

        // Tools are inventory and stay separate from that.
        $this->assertSame([
            ['name' => 'rsync', 'version' => null],
            ['name' => 'borg', 'version' => 'borg 1.2.4'],
        ], $result['tools']);
    }

    #[Test]
    public function a_cron_d_line_keeps_its_command_and_loses_the_user_column(): void
    {
        $out = "##LL:tools\n##LL:cron\n"
            ."/etc/cron.d/plesk|2,17,32,47 * * * * root [ -x /opt/psa/admin/sbin/backupmng ] && /opt/psa/admin/sbin/backupmng\n"
            ."##LL:end\n";

        $job = $this->inspect([['ok' => true, 'out' => $out]])['schedules'][0];

        $this->assertSame('plesk', $job['name']);
        $this->assertSame('2,17,32,47 * * * *', $job['schedule']);
        // The user column belongs to cron.d, not to the command; keeping it
        // would print "root [ -x ..." as the thing being run.
        $this->assertStringStartsWith('[ -x /opt/psa', $job['runs']);
    }

    #[Test]
    public function a_password_on_a_cron_line_is_not_printed(): void
    {
        $out = "##LL:tools\n##LL:cron\n"
            ."root|0 2 * * * mysqldump -uroot -pHunter2 db > /srv/db.sql\n"
            ."root|0 3 * * * PGPASSWORD=s3cret pg_dump app > /srv/app.sql\n"
            ."##LL:repos\nssh://backup:letmein@store.example/./repo\n"
            ."##LL:end\n";

        $result = $this->inspect([['ok' => true, 'out' => $out]]);
        $printed = json_encode($result) ?: '';

        // A cron line is written by somebody who did not expect it to be shown.
        $this->assertStringNotContainsString('Hunter2', $printed);
        $this->assertStringNotContainsString('s3cret', $printed);
        $this->assertStringNotContainsString('letmein', $printed);
        $this->assertStringContainsString('mysqldump', $printed);
        $this->assertStringContainsString('store.example', $printed);
    }

    #[Test]
    public function a_timer_is_read_with_its_unit_and_not_its_timestamps(): void
    {
        $out = "##LL:tools\n##LL:timers\n"
            ."Mon 2026-08-24 00:00:00 CEST 6h left  Sun 2026-08-23 00:00:01 CEST 17h ago  dpkg-db-backup.timer  dpkg-db-backup.service\n"
            ."##LL:end\n";

        $job = $this->inspect([['ok' => true, 'out' => $out]])['schedules'][0];

        // Both timestamps carry spaces, so the two unit names at the end are
        // the only reliable anchor in that line.
        $this->assertSame('timer', $job['kind']);
        $this->assertSame('dpkg-db-backup.timer', $job['name']);
        $this->assertSame('dpkg-db-backup.service', $job['runs']);
    }

    #[Test]
    public function an_agent_run_still_going_has_no_outcome_rather_than_a_bad_one(): void
    {
        $out = "##LL:tools\nacrocmd\n"
            ."##LL:agents\nacronis_mms.service\tactive\trunning\n"
            ."##LL:acronis\n"
            ."Name                  Machine               State                 Progress    Start Time            Elapsed Time  Estimated Time  GUID                  Resource              Result\n"
            ."Backing up            apps.example          completed             100%        23.08.2026 16:18:41   00:03:21      00:03:21        B7965F5E-...          etc, home             Succeeded\n"
            ."Backing up            apps.example          running               42%         23.08.2026 17:18:41   00:01:02      00:02:30\n"
            ."##LL:end\n";

        $result = $this->inspect([['ok' => true, 'out' => $out]]);

        $this->assertTrue($result['agents'][0]['active']);
        $this->assertSame('Succeeded', $result['activities'][0]['result']);
        $this->assertNull($result['activities'][1]['result']);
    }

    #[Test]
    public function a_host_with_nothing_scheduled_says_so_rather_than_looking_empty(): void
    {
        $out = "##LL:tools\nrsync\n##LL:agents\n__absent__\n##LL:timers\n__absent__\n##LL:cron\n##LL:end\n";

        $result = $this->inspect([['ok' => true, 'out' => $out]]);

        // rsync being installed is not a backup, and the interface says exactly
        // that when there is nothing scheduled to go with it.
        $this->assertSame([], $result['schedules']);
        $this->assertSame([], $result['agents']);
        $this->assertSame([['name' => 'rsync', 'version' => null]], $result['tools']);
    }

    #[Test]
    public function no_second_round_trip_when_no_job_writes_a_log(): void
    {
        $probe = new VpnRecordingProbe([['ok' => true, 'out' => "##LL:tools\n##LL:cron\nroot|0 2 * * * /opt/b.sh\n##LL:end\n"]]);

        (new BackupInspector($probe))->inspect($this->server());

        // The stat call exists to date the logs; with none to date, asking is
        // a handshake for nothing.
        $this->assertStringNotContainsString('stat -c', $probe->sent());
    }

    #[Test]
    public function an_unreachable_host_says_so_instead_of_reporting_no_backups(): void
    {
        $result = $this->inspect([['ok' => false, 'out' => '']]);

        $this->assertFalse($result['ok']);
        $this->assertSame('unreachable', $result['error']);
    }
}
