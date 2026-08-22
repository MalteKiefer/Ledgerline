import { describe, expect, it } from 'vitest';
import {
  DISK_WARN_PCT,
  diskNote,
  formatGib,
  formatUptime,
  fullestDisk,
  memoryNote,
  needsAttention,
  severity,
  swapNote,
  swapPct,
} from '../server-facts';
import type { Server, ServerFacts } from '@spa/stores/servers';

function facts(over: Partial<ServerFacts> = {}): ServerFacts {
  return {
    hostname: 'srv',
    os: { name: 'Debian GNU/Linux 12', id: 'debian', id_like: null, version: '12' },
    kernel: '6.1.0-18-amd64',
    arch: 'x86_64',
    uptime_s: 3600,
    load: [0.1, 0.2, 0.3],
    cpu: { cores: 4, used_pct: null, model: 'Ryzen 5 3600' },
    mem: { total_kb: 16000000, available_kb: 8000000, used_pct: 50, swap_total_kb: 0, swap_used_kb: 0 },
    disks: [],
    disk_max_pct: null,
    reboot_required: false,
    failed_units: [],
    ports: [],
    containers: [],
    updates: null,
    addresses: [],
    virt: null,
    boot_at: null,
    sessions: [],
    processes: [],
    temp_c: null,
    network: { gateway: null, dns: [], search: null, interfaces: [] },
    ...over,
  };
}

function server(over: Partial<Server> = {}): Server {
  return {
    id: 1,
    name: 'web01',
    host: '10.0.0.9',
    port: 22,
    username: 'monitor',
    auth_type: 'key',
    group: null,
    note: null,
    enabled: true,
    restricted_key: false,
    account_created: true,
    monitor_ports: [],
    host_fingerprint: 'SHA256:x',
    status: { ok: true, error: null, collected_at: '2026-08-20T10:00:00Z', duration_ms: 100 },
    facts: facts(),
    ...over,
  };
}

describe('severity', () => {
  it('treats a never-probed server as unknown, not healthy', () => {
    // The dashboard groups this with the unreachable ones on purpose: "we have
    // no idea" must not render as green.
    expect(severity(server({ status: null, facts: null }))).toBe('unknown');
    expect(needsAttention(server({ status: null, facts: null }))).toBe(true);
  });

  it('ranks unreachable above every fact-derived problem', () => {
    const down = server({
      status: { ok: false, error: 'auth_failed', collected_at: '2026-08-20T10:00:00Z', duration_ms: 5 },
      facts: facts({ disk_max_pct: 99, failed_units: ['nginx.service'], reboot_required: true }),
    });
    expect(severity(down)).toBe('down');
  });

  it('warns on a full filesystem, a failed unit or a pending reboot', () => {
    expect(severity(server({ facts: facts({ disk_max_pct: DISK_WARN_PCT }) }))).toBe('warn');
    expect(severity(server({ facts: facts({ failed_units: ['postfix.service'] }) }))).toBe('warn');
    expect(severity(server({ facts: facts({ reboot_required: true }) }))).toBe('warn');
  });

  it('stays ok just below the disk threshold', () => {
    expect(severity(server({ facts: facts({ disk_max_pct: DISK_WARN_PCT - 0.1 }) }))).toBe('ok');
    expect(needsAttention(server())).toBe(false);
  });

  it('is unknown when a successful run somehow carries no facts', () => {
    expect(severity(server({ facts: null }))).toBe('unknown');
  });
});

describe('formatUptime', () => {
  it('shows days once there is at least one', () => {
    expect(formatUptime(9 * 86400 + 3 * 3600)).toBe('9 d 3 h');
  });

  it('shows hours and minutes below a day', () => {
    expect(formatUptime(5 * 3600 + 42 * 60)).toBe('5 h 42 min');
  });

  it('shows minutes for a fresh boot', () => {
    expect(formatUptime(120)).toBe('2 min');
  });

  it('renders no value as a dash rather than 0 min', () => {
    expect(formatUptime(null)).toBe('—');
    expect(formatUptime(-1)).toBe('—');
  });
});

describe('memory and disk notes', () => {
  it('derives used memory from available, not free', () => {
    expect(memoryNote(facts())).toBe('7.6 GiB / 15.3 GiB');
  });

  it('renders an incomplete meminfo as a dash', () => {
    const f = facts();
    f.mem.available_kb = null;
    expect(memoryNote(f)).toBe('—');
  });

  it('returns null swap usage when the host has no swap', () => {
    // 0% would claim the host has swap and is not using it.
    expect(swapPct(facts())).toBeNull();
    expect(swapPct(facts({ mem: { ...facts().mem, swap_total_kb: 2097152, swap_used_kb: 1048576 } }))).toBe(50);
    expect(swapNote(facts({ mem: { ...facts().mem, swap_total_kb: 2097152, swap_used_kb: 1048576 } }))).toBe('1.0 GiB / 2.0 GiB');
  });

  it('formats kibibytes as GiB', () => {
    expect(formatGib(1048576)).toBe('1.0 GiB');
    expect(formatGib(null)).toBe('—');
    expect(formatGib(undefined)).toBe('—');
  });
});

describe('fullestDisk', () => {
  const a = { fs: '/dev/sda1', mount: '/', size_kb: 100, used_kb: 60, avail_kb: 40, used_pct: 60 };
  const b = { fs: '/dev/sdb1', mount: '/srv', size_kb: 100, used_kb: 97, avail_kb: 3, used_pct: 97 };

  it('picks the filesystem under the most pressure', () => {
    expect(fullestDisk(facts({ disks: [a, b] }))?.mount).toBe('/srv');
    expect(fullestDisk(facts({ disks: [b, a] }))?.mount).toBe('/srv');
  });

  it('returns null for a host that reported no filesystems', () => {
    expect(fullestDisk(facts())).toBeNull();
  });

  it('notes used against total', () => {
    expect(diskNote({ ...a, used_kb: 1048576, size_kb: 2097152 })).toBe('1.0 GiB / 2.0 GiB');
  });
});
