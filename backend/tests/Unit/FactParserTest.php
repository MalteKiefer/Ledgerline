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
        // The image comes through empty here because this fixture predates the
        // column; what matters is that the key exists, since the image is what
        // tells the role detector what a container actually is.
        $this->assertSame(
            [['name' => 'ledgerline-app-1', 'status' => 'Up 3 days (healthy)', 'image' => '']],
            $f['containers'],
        );
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
        // Structured, because the tty is what an operator acts on: ending a
        // session means signalling that terminal, and digging it back out of a
        // display string would be guessing at our own output.
        $this->assertSame([[
            'user' => 'malte',
            'tty' => 'pts/0',
            'since' => '2026-08-21 19:30',
            'from' => '10.0.0.5',
        ]], $f['sessions']);
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

    public function test_cpu_utilisation_is_a_delta_between_two_proc_stat_samples(): void
    {
        // A single read of /proc/stat gives the average since boot, which never
        // moves on a long-lived host. Only the difference is a current figure.
        $out = <<<'OUT'
        ##LL:cpu
        4
        model name	: Ryzen 5 3600
        ##LL:cpustat
        cpu  1000 0 1000 8000 0 0 0 0 0 0
        cpu  1100 0 1100 8800 0 0 0 0 0 0
        ##LL:end
        OUT;

        $f = (new FactParser)->parse($out);

        // 200 busy jiffies against 1000 total in the interval.
        $this->assertSame(20.0, $f['cpu']['used_pct']);
        $this->assertSame(4, $f['cpu']['cores']);
    }

    public function test_cpu_utilisation_is_null_without_a_second_sample(): void
    {
        // Better an absent number than one computed from a single read, which
        // would report the since-boot average as if it were current.
        $out = <<<'OUT'
        ##LL:cpu
        2
        ##LL:cpustat
        cpu  1000 0 1000 8000 0 0 0 0 0 0
        ##LL:end
        OUT;

        $this->assertNull((new FactParser)->parse($out)['cpu']['used_pct']);
    }

    public function test_id_like_is_captured_for_derivative_distributions(): void
    {
        // A derivative we do not know by name is still recognisable through what
        // it is built on, which is exactly what ID_LIKE is for.
        $out = <<<'OUT'
        ##LL:os
        PRETTY_NAME="Pop!_OS 22.04 LTS"
        ID=pop
        ID_LIKE="ubuntu debian"
        ##LL:end
        OUT;

        $f = (new FactParser)->parse($out);
        $this->assertSame('pop', $f['os']['id']);
        $this->assertSame('ubuntu debian', $f['os']['id_like']);
    }

    public function test_network_reads_the_default_route_resolvers_and_counters(): void
    {
        // The three questions asked of a host that is up but reaching nothing:
        // where does it route, who does it ask, and has anything moved.
        $out = <<<'OUT'
        ##LL:gateway
        default via 192.168.3.1 dev eth0 proto dhcp metric 100
        ##LL:dns
        nameserver 192.168.3.1
        nameserver 1.1.1.1
        search fritz.box
        ##LL:netstat
          eth0: 1024000 900 0 0 0 0 0 0 2048000 700 0 0 0 0 0 0
            lo: 500 5 0 0 0 0 0 0 500 5 0 0 0 0 0 0
        ##LL:end
        OUT;

        $n = (new FactParser)->parse($out)['network'];

        $this->assertSame('192.168.3.1', $n['gateway']);
        $this->assertSame(['192.168.3.1', '1.1.1.1'], $n['dns']);
        $this->assertSame('fritz.box', $n['search']);

        // Loopback is not a network interface anyone is asking about.
        $this->assertCount(1, $n['interfaces']);
        $this->assertSame('eth0', $n['interfaces'][0]['name']);
        $this->assertSame(1024000, $n['interfaces'][0]['rx_bytes']);
        $this->assertSame(2048000, $n['interfaces'][0]['tx_bytes']);
    }

    public function test_interfaces_carry_their_kind_addresses_gateway_and_resolvers(): void
    {
        // A Docker host is mostly bridges and veth pairs. A list that calls
        // them all "interface" hides the one the operator cares about.
        $out = <<<'OUT'
        ##LL:gateway
        default via 192.168.3.1 dev eth0 proto dhcp metric 100
        ##LL:dns
        nameserver 192.168.3.1
        ##LL:netstat
          eth0: 1024000 900 0 0 0 0 0 0 2048000 700 0 0 0 0 0 0
        docker0: 100 1 0 0 0 0 0 0 200 2 0 0 0 0 0 0
        vethab12: 5 1 0 0 0 0 0 0 5 1 0 0 0 0 0 0
           wg0: 7 1 0 0 0 0 0 0 7 1 0 0 0 0 0 0
        ##LL:iflink
        2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc fq_codel state UP link/ether aa:bb:cc:dd:ee:ff brd ff:ff:ff:ff:ff:ff
        3: docker0: <NO-CARRIER,BROADCAST,MULTICAST> mtu 1500 qdisc noqueue state DOWN link/ether 02:42:0a:0b:0c:0d brd ff:ff:ff:ff:ff:ff
        ##LL:ifaddr
        2: eth0    inet 192.168.3.5/24 brd 192.168.3.255 scope global eth0
        2: eth0    inet6 fe80::1/64 scope link
        2: eth0    inet6 2001:db8::5/64 scope global
        ##LL:routes
        default via 192.168.3.1 dev eth0 proto dhcp metric 100
        ##LL:resolved
        Link 2 (eth0): 192.168.3.1 1.1.1.1
        ##LL:bridges
        /sys/class/net/docker0/bridge/bridge_id
        ##LL:end
        OUT;

        $i = collect((new FactParser)->parse($out)['network']['interfaces'])->keyBy('name');

        $this->assertSame('ethernet', $i['eth0']['kind']);
        $this->assertTrue($i['eth0']['up']);
        $this->assertSame(1500, $i['eth0']['mtu']);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $i['eth0']['mac']);
        // Link-local v6 is on every interface and tells a reader nothing.
        $this->assertSame(['192.168.3.5/24', '2001:db8::5/64'], $i['eth0']['addresses']);
        $this->assertSame('192.168.3.1', $i['eth0']['gateway']);
        $this->assertSame(['192.168.3.1', '1.1.1.1'], $i['eth0']['dns']);

        // From the kernel, not from the name: docker0 has a bridge/ directory.
        $this->assertSame('bridge', $i['docker0']['kind']);
        $this->assertFalse($i['docker0']['up']);
        $this->assertSame('veth', $i['vethab12']['kind']);
        $this->assertSame('wireguard', $i['wg0']['kind']);

        // An interface the host said nothing about must read null, not false:
        // "down" and "not reported" are different answers.
        $this->assertNull($i['wg0']['up']);
        $this->assertSame([], $i['wg0']['addresses']);
    }

    public function test_network_is_empty_rather_than_wrong_on_a_host_without_it(): void
    {
        $n = (new FactParser)->parse('##LL:end')['network'];

        $this->assertNull($n['gateway']);
        $this->assertSame([], $n['dns']);
        $this->assertSame([], $n['interfaces']);
    }

    /**
     * lsblk -P is used precisely because a model name contains spaces: column
     * splitting turns "MARVELL Raid VD" into three fields and loses the disk.
     */
    public function test_storage_keeps_a_model_name_that_contains_spaces(): void
    {
        $out = implode("\n", [
            '##LL:blockdev',
            'NAME="sda" TYPE="disk" SIZE="4000630046720" ROTA="1" MODEL="MARVELL Raid VD"',
            '##LL:smart',
            '__absent__',
            '##LL:end',
        ]);

        $disks = (new FactParser)->parse($out)['storage'];

        $this->assertCount(1, $disks);
        $this->assertSame('sda', $disks[0]['name']);
        $this->assertSame('MARVELL Raid VD', $disks[0]['model']);
        $this->assertSame(4000630046720, $disks[0]['size_b']);
        $this->assertTrue($disks[0]['rotational']);
    }

    public function test_a_disk_without_smartctl_reads_unknown_rather_than_ok(): void
    {
        // The important one: smartmontools is missing on plenty of machines,
        // and a disk whose state could not be read is not a healthy disk.
        // Reporting the two the same way is how a dying drive goes unnoticed.
        $out = implode("\n", [
            '##LL:blockdev',
            'NAME="sda" TYPE="disk" SIZE="500107862016" ROTA="0" MODEL="Samsung SSD 860"',
            '##LL:smart',
            '__absent__',
            '##LL:end',
        ]);

        $disk = (new FactParser)->parse($out)['storage'][0];

        $this->assertSame('unknown', $disk['health']);
        $this->assertNull($disk['temp_c']);
        $this->assertNull($disk['reallocated']);
        $this->assertNull($disk['pending']);
        $this->assertFalse($disk['rotational']);
    }

    public function test_smart_health_covers_passed_failed_and_unreadable_per_device(): void
    {
        // Three disks in one run: the verdicts must not bleed between blocks,
        // and a controller that refuses to pass SMART through is its own state
        // rather than a healthy drive.
        $out = implode("\n", [
            '##LL:blockdev',
            'NAME="sda" TYPE="disk" SIZE="1000204886016" ROTA="1" MODEL="WDC WD10EZEX"',
            'NAME="sdb" TYPE="disk" SIZE="2000398934016" ROTA="1" MODEL="ST2000DM008"',
            'NAME="sdc" TYPE="disk" SIZE="500107862016" ROTA="0" MODEL="Crucial CT500"',
            '##LL:smart',
            '##DEV:/dev/sda',
            'SMART overall-health self-assessment test result: PASSED',
            '##DEV:/dev/sdb',
            'SMART overall-health self-assessment test result: FAILED!',
            '##DEV:/dev/sdc',
            '/dev/sdc: Unable to detect device type',
            'Please specify device type with the -d option.',
            '##LL:end',
        ]);

        $health = array_column((new FactParser)->parse($out)['storage'], 'health', 'name');

        $this->assertSame(['sda' => 'ok', 'sdb' => 'failing', 'sdc' => 'unreadable'], $health);
    }

    public function test_a_controller_that_refuses_smart_is_unreadable(): void
    {
        $out = implode("\n", [
            '##LL:blockdev',
            'NAME="sda" TYPE="disk" SIZE="4000630046720" ROTA="1" MODEL="MARVELL Raid VD"',
            '##LL:smart',
            '##DEV:/dev/sda',
            'Read Device Identity failed: Operation not supported',
            'A mandatory SMART command failed, exiting.',
            '##LL:end',
        ]);

        $this->assertSame('unreadable', (new FactParser)->parse($out)['storage'][0]['health']);
    }

    public function test_reallocated_and_pending_sectors_come_from_the_attribute_table(): void
    {
        // Column 10 is RAW_VALUE. Those two attributes are the ones that
        // actually predict a failure: a drive that has started remapping is on
        // its way out whatever the overall verdict says.
        $out = implode("\n", [
            '##LL:blockdev',
            'NAME="sda" TYPE="disk" SIZE="1000204886016" ROTA="1" MODEL="WDC WD10EZEX"',
            'NAME="sdb" TYPE="disk" SIZE="1000204886016" ROTA="1" MODEL="WDC WD10EZEX"',
            '##LL:smart',
            '##DEV:/dev/sda',
            'SMART overall-health self-assessment test result: PASSED',
            '',
            'SMART Attributes Data Structure revision number: 16',
            'Vendor Specific SMART Attributes with Thresholds:',
            'ID# ATTRIBUTE_NAME          FLAG     VALUE WORST THRESH TYPE      UPDATED  WHEN_FAILED RAW_VALUE',
            '  1 Raw_Read_Error_Rate     0x000f   118   099   006    Pre-fail  Always       -       178534728',
            '  5 Reallocated_Sector_Ct   0x0033   100   100   010    Pre-fail  Always       -       8',
            '  9 Power_On_Hours          0x0032   089   089   000    Old_age   Always       -       10321',
            '194 Temperature_Celsius     0x0022   032   040   000    Old_age   Always       -       32 (Min/Max 20/40)',
            '197 Current_Pending_Sector  0x0012   100   100   000    Old_age   Always       -       2',
            '##DEV:/dev/sdb',
            'SMART overall-health self-assessment test result: PASSED',
            'ID# ATTRIBUTE_NAME          FLAG     VALUE WORST THRESH TYPE      UPDATED  WHEN_FAILED RAW_VALUE',
            '  9 Power_On_Hours          0x0032   100   100   000    Old_age   Always       -       12',
            '##LL:end',
        ]);

        $disks = array_column((new FactParser)->parse($out)['storage'], null, 'name');

        $this->assertSame(8, $disks['sda']['reallocated']);
        $this->assertSame(2, $disks['sda']['pending']);
        $this->assertSame(10321, $disks['sda']['hours']);
        $this->assertSame(32.0, $disks['sda']['temp_c']);

        // The second device is its own block: no attribute may leak across.
        $this->assertSame(12, $disks['sdb']['hours']);
        $this->assertNull($disks['sdb']['reallocated']);
        $this->assertNull($disks['sdb']['pending']);
    }

    public function test_a_degraded_md_array_is_flagged_and_a_clean_one_is_not(): void
    {
        // The classic silent failure: everything still works, right up until
        // the second disk goes. The [U_] marker sits on the block line beneath
        // the array, not on the header line.
        $out = implode("\n", [
            '##LL:mdstat',
            'Personalities : [raid1] [raid10]',
            'md1 : active raid1 sda2[0] sdb2[1]',
            '      975296 blocks super 1.2 [2/2] [UU]',
            '',
            'md2 : active raid1 sda3[0](F) sdb3[1]',
            '      527000000 blocks super 1.2 [2/1] [U_]',
            '',
            'md0 : inactive sda1[0](S)',
            '      1046528 blocks super 1.2',
            '',
            'unused devices: <none>',
            '##LL:zpool',
            '__absent__',
            '##LL:end',
        ]);

        $arrays = array_column((new FactParser)->parse($out)['arrays'], null, 'name');

        $this->assertCount(3, $arrays);
        $this->assertSame('mdraid', $arrays['md1']['kind']);
        $this->assertFalse($arrays['md1']['degraded']);
        $this->assertTrue($arrays['md2']['degraded'], 'an array reporting [U_] is degraded');
        // "inactive" is not a state anyone should have to read past.
        $this->assertTrue($arrays['md0']['degraded']);
        $this->assertSame('inactive', $arrays['md0']['state']);
    }

    public function test_zpool_health_other_than_online_is_degraded(): void
    {
        $out = implode("\n", [
            '##LL:mdstat',
            '',
            '##LL:zpool',
            "tank\t1.81T\t500G\t1.31T\tONLINE\t5%",
            "backup\t3.62T\t2.00T\t1.62T\tDEGRADED\t12%",
            "old\t900G\t100G\t800G\tFAULTED\t1%",
            '##LL:end',
        ]);

        $pools = array_column((new FactParser)->parse($out)['arrays'], null, 'name');

        $this->assertSame('zfs', $pools['tank']['kind']);
        $this->assertFalse($pools['tank']['degraded']);
        $this->assertSame('1.81T total, 500G used, 1.31T free', $pools['tank']['detail']);
        $this->assertTrue($pools['backup']['degraded']);
        $this->assertTrue($pools['old']['degraded']);
    }

    public function test_sensors_prefer_a_label_and_discard_implausible_readings(): void
    {
        $out = implode("\n", [
            '##LL:hwmon',
            "k10temp\ttemp1_input\t\t78500",
            "jc42\ttemp1_input\t\t42500",
            "amdgpu\ttemp1_input\tedge\t78000",
            // A sensor reading zero is not wired up rather than frozen, and
            // 250 degrees is a bad read rather than a fire.
            "nct6797\ttemp5_input\t\t0",
            "nct6797\ttemp6_input\tAUXTIN3\t250000",
            '##LL:end',
        ]);

        $sensors = (new FactParser)->parse($out)['sensors'];

        $this->assertCount(3, $sensors);
        // Without a label the sysfs file name stands in, minus the '_input'
        // suffix, which means nothing to a reader.
        $this->assertSame(['chip' => 'k10temp', 'label' => 'temp1', 'temp_c' => 78.5], $sensors[0]);
        $this->assertSame(42.5, $sensors[1]['temp_c']);
        // A label is what a human reads; the sensor name is only the fallback.
        $this->assertSame('edge', $sensors[2]['label']);
        $this->assertSame(78.0, $sensors[2]['temp_c']);
    }

    public function test_missing_hardware_sections_yield_empty_lists_rather_than_throwing(): void
    {
        // A container, a VM without lsblk, a host without smartmontools: every
        // one of these sections is optional.
        $f = (new FactParser)->parse("##LL:blockdev\n__absent__\n##LL:smart\n__absent__\n##LL:zpool\n__absent__\n##LL:end");

        $this->assertSame([], $f['storage']);
        $this->assertSame([], $f['arrays']);
        $this->assertSame([], $f['sensors']);

        $empty = (new FactParser)->parse('');
        $this->assertSame([], $empty['storage']);
        $this->assertSame([], $empty['arrays']);
        $this->assertSame([], $empty['sensors']);
    }
}
