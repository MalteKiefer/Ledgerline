<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Servers\FactParser;
use PHPUnit\Framework\TestCase;

/**
 * The parser is where the distribution matrix lives, so it is tested from
 * fixtures rather than against a real host: pure text in, snapshot out.
 */
class FactParserTest extends TestCase
{
    private function debianOutput(): string
    {
        // Deliberately includes an MOTD before the first marker — a real login
        // prints one, and it must not end up in any section.
        return <<<'OUT'
        Linux srv 6.1.0 #1 SMP
        Last login: Tue Aug 19 08:00:00 2026
        ##LL:hostname
        srv
        ##LL:os
        PRETTY_NAME="Debian GNU/Linux 12 (bookworm)"
        NAME="Debian GNU/Linux"
        VERSION_ID="12"
        ID=debian
        ##LL:kernel
        Linux 6.1.0-18-amd64 x86_64
        ##LL:uptime
        851234.42 3401234.10
        ##LL:load
        0.42 0.31 0.28 1/512 90210
        ##LL:mem
        MemTotal:       16316412 kB
        MemFree:          412344 kB
        MemAvailable:    9812344 kB
        SwapTotal:       2097148 kB
        SwapFree:        2097148 kB
        ##LL:disk
        Filesystem     1024-blocks      Used Available Capacity Mounted on
        /dev/sda1        102687672  61612604  35814920      64% /
        tmpfs              8158204         0   8158204       0% /dev/shm
        /dev/sdb1       1922728840 1863010000   1720000      99% /srv/data
        ##LL:cpu
        4
        model name	: AMD Ryzen 5 3600
        ##LL:reboot
        yes
        ##LL:failed
        nginx.service loaded failed failed A high performance web server
        ##LL:ports
        LISTEN 0      511          0.0.0.0:80        0.0.0.0:*
        LISTEN 0      128          0.0.0.0:22        0.0.0.0:*
        ##LL:containers
        ledgerline-app-1|Up 3 days (healthy)
        ##LL:updates
        7
        ##LL:end
        OUT;
    }

    public function test_it_parses_a_debian_snapshot(): void
    {
        $f = (new FactParser)->parse($this->debianOutput());

        $this->assertSame('srv', $f['hostname']);
        $this->assertSame('Debian GNU/Linux 12 (bookworm)', $f['os']['name']);
        $this->assertSame('debian', $f['os']['id']);
        $this->assertSame('6.1.0-18-amd64', $f['kernel']);
        $this->assertSame('x86_64', $f['arch']);
        $this->assertSame(851234, $f['uptime_s']);
        $this->assertSame([0.42, 0.31, 0.28], $f['load']);
        $this->assertSame(4, $f['cpu']['cores']);
        $this->assertSame('AMD Ryzen 5 3600', $f['cpu']['model']);
        $this->assertTrue($f['reboot_required']);
        $this->assertSame(['nginx.service'], $f['failed_units']);
        $this->assertSame(['0.0.0.0:80', '0.0.0.0:22'], $f['ports']);
        $this->assertSame([['name' => 'ledgerline-app-1', 'status' => 'Up 3 days (healthy)']], $f['containers']);
        $this->assertSame(7, $f['updates']);
    }

    public function test_memory_uses_available_not_free(): void
    {
        // MemFree alone reads as 97% used on a healthy machine; MemAvailable is
        // the figure an operator actually acts on.
        $f = (new FactParser)->parse($this->debianOutput());

        $this->assertSame(16316412, $f['mem']['total_kb']);
        $this->assertSame(9812344, $f['mem']['available_kb']);
        $this->assertSame(39.9, $f['mem']['used_pct']);
        $this->assertSame(0, $f['mem']['swap_used_kb']);
    }

    public function test_pseudo_filesystems_are_dropped_and_the_fullest_is_surfaced(): void
    {
        $f = (new FactParser)->parse($this->debianOutput());

        $mounts = array_column($f['disks'], 'mount');
        $this->assertSame(['/', '/srv/data'], $mounts);
        $this->assertNotContains('/dev/shm', $mounts);
        // used/(used+avail) like df's Capacity column, not used/size.
        $this->assertSame(99.9, $f['disk_max_pct']);
    }

    public function test_a_docker_host_reports_each_filesystem_once(): void
    {
        // Every overlay2 layer is its own df line reporting the figures of the
        // disk underneath. Listed verbatim that is fifteen identical bars saying
        // 399/502 GiB, which tells the reader nothing and buries the mounts that
        // do differ — /boot and the storage box below.
        $out = implode('
', [
            '##LL:disk',
            'Filesystem     1024-blocks      Used Available Capacity Mounted on',
            '/dev/md2         527000000 419000000  81000000      84% /',
            '/dev/md1            970000    210000    700000      24% /boot',
            'tmpfs              8158204         0   8158204       0% /dev/shm',
            '/dev/md2         527000000 419000000  81000000      84% /var/lib/docker/overlay2/9cbf2fcf26f/merged',
            '/dev/md2         527000000 419000000  81000000      84% /var/lib/docker/overlay2/3578f1b14aa/merged',
            'overlay          527000000 419000000  81000000      84% /var/lib/docker/overlay2/760c6c446bd/merged',
            '//box/backup    1066000000 296000000 770000000      28% /mnt/storagebox',
            '##LL:end',
        ]);

        $disks = (new FactParser)->parse($out)['disks'];

        $this->assertSame(['/', '/boot', '/mnt/storagebox'], array_column($disks, 'mount'));
        // The fullest real filesystem still drives the status, not a phantom
        // copy. 83.8 is df's 84% before its rounding up.
        $this->assertSame(83.8, (new FactParser)->parse($out)['disk_max_pct']);
    }

    public function test_it_reads_the_extra_host_details(): void
    {
        $out = implode('
', [
            '##LL:ip', 'eth0 192.168.3.200/24', 'wg0 10.8.0.2/32',
            '##LL:virt', 'kvm',
            '##LL:boot', 'btime 1755000000',
            '##LL:sessions', 'malte pts/0 2026-08-21 19:30 (10.0.0.5)',
            '##LL:procs', '  812340 mariadbd', '  204800 php-fpm',
            '##LL:temp', '48200',
            '##LL:end',
        ]);

        $f = (new FactParser)->parse($out);

        $this->assertSame(['eth0 192.168.3.200/24', 'wg0 10.8.0.2/32'], $f['addresses']);
        $this->assertSame('kvm', $f['virt']);
        $this->assertStringStartsWith('2025-', (string) $f['boot_at']);
        $this->assertSame(['malte pts/0 2026-08-21 19:30 (10.0.0.5)'], $f['sessions']);
        $this->assertSame([['name' => 'mariadbd', 'rss_kb' => 812340], ['name' => 'php-fpm', 'rss_kb' => 204800]], $f['processes']);
        $this->assertSame(48.2, $f['temp_c']);
    }

    public function test_bare_metal_reports_no_hypervisor_rather_than_the_word_none(): void
    {
        // systemd-detect-virt says "none" on bare metal; showing that as a
        // virtualisation type would be worse than showing nothing.
        $this->assertNull((new FactParser)->parse('##LL:virt
none
##LL:end')['virt']);
    }

    public function test_a_host_without_systemd_or_docker_omits_those_sections(): void
    {
        // Alpine: no systemctl, no docker, apk answers the update count.
        $out = implode("\n", [
            '##LL:hostname', 'edge',
            '##LL:os', 'PRETTY_NAME="Alpine Linux v3.20"', 'ID=alpine',
            '##LL:kernel', 'Linux 6.6.32-0-lts aarch64',
            '##LL:uptime', '120.5 240.0',
            '##LL:load', '0.00 0.01 0.05 1/100 200',
            '##LL:mem', 'MemTotal: 1024000 kB', 'MemAvailable: 900000 kB',
            '##LL:disk', 'Filesystem 1024-blocks Used Available Capacity Mounted on', '/dev/vda1 10000 5000 5000 50% /',
            '##LL:cpu', '2',
            '##LL:reboot', 'no',
            '##LL:failed', '',
            '##LL:ports', '',
            '##LL:containers', '',
            '##LL:updates', '3',
            '##LL:end',
        ]);

        $f = (new FactParser)->parse($out);

        $this->assertSame('Alpine Linux v3.20', $f['os']['name']);
        $this->assertSame('aarch64', $f['arch']);
        $this->assertFalse($f['reboot_required']);
        $this->assertSame([], $f['failed_units']);
        $this->assertSame([], $f['containers']);
        $this->assertNull($f['cpu']['model']);
        $this->assertSame(3, $f['updates']);
    }

    public function test_unknown_update_count_is_null_not_zero(): void
    {
        // No supported package manager answered. Zero would read as "fully
        // patched", which is a different and much more reassuring claim.
        $f = (new FactParser)->parse("##LL:updates\n\n##LL:end");

        $this->assertNull($f['updates']);
    }

    public function test_empty_output_yields_a_snapshot_of_nulls_rather_than_throwing(): void
    {
        $f = (new FactParser)->parse('');

        $this->assertNull($f['hostname']);
        $this->assertNull($f['kernel']);
        $this->assertSame([], $f['disks']);
        $this->assertNull($f['disk_max_pct']);
    }
}
