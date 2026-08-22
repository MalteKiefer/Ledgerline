import { trans as t } from 'laravel-vue-i18n';
import type { Server, ServerDisk, ServerFacts } from '@spa/stores/servers';

/**
 * Pure presentation of a server snapshot, shared by the Servers page and the
 * dashboard tile. Kept out of both components so the severity rules have one
 * definition — a host counted as "warning" on the dashboard must not read as
 * healthy on the module page.
 */

/** A filesystem at or above this share counts as pressure. */
export const DISK_WARN_PCT = 90;

export type Severity = 'unknown' | 'down' | 'warn' | 'ok';

/**
 * Worst-first: unreachable beats a full disk, which beats a failed unit or a
 * pending reboot. A server that has never been probed is `unknown`, not healthy.
 */
export function severity(server: Server): Severity {
  if (!server.status) return 'unknown';
  if (!server.status.ok) return 'down';
  const f = server.facts;
  if (!f) return 'unknown';
  if ((f.disk_max_pct ?? 0) >= DISK_WARN_PCT) return 'warn';
  if (f.failed_units.length > 0) return 'warn';
  if (f.reboot_required) return 'warn';
  return 'ok';
}

/** Anything the owner might want to act on. */
export function needsAttention(server: Server): boolean {
  return severity(server) !== 'ok';
}

/** Human uptime. Days once there is at least one, otherwise hours, else minutes. */
export function formatUptime(seconds: number | null): string {
  if (seconds === null || seconds < 0) return '—';
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  if (days > 0) return `${days} d ${hours} h`;
  if (hours > 0) return `${hours} h ${minutes} min`;
  return `${minutes} min`;
}

/** Kibibytes as GiB with one decimal — the unit df and /proc report in. */
export function formatGib(kb: number | null | undefined): string {
  return kb === null || kb === undefined ? '—' : `${(kb / 1048576).toFixed(1)} GiB`;
}

/**
 * A byte figure at the scale a disk is sold in.
 *
 * Decimal, not binary: a drive labelled 4 TB holds 4000 GB, and reporting it as
 * 3.6 TiB makes the app disagree with the sticker for no gain.
 */
export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || bytes <= 0) return "—";
  const units = ["B", "KB", "MB", "GB", "TB", "PB"];
  let value = bytes;
  let i = 0;
  while (value >= 1000 && i < units.length - 1) {
    value /= 1000;
    i += 1;
  }

  return `${value.toFixed(value >= 100 || i === 0 ? 0 : 1)} ${units[i]}`;
}

/** "used / total", derived from MemAvailable rather than MemFree. */
export function memoryNote(facts: ServerFacts): string {
  const { total_kb: total, available_kb: available } = facts.mem;
  if (total === null || available === null) return '—';
  return `${formatGib(total - available)} / ${formatGib(total)}`;
}

/** Null when the host has no swap configured — 0% would imply it has some. */
export function swapPct(facts: ServerFacts): number | null {
  const { swap_total_kb: total, swap_used_kb: used } = facts.mem;
  if (!total || used === null) return null;
  return Math.round((used / total) * 1000) / 10;
}

export function swapNote(facts: ServerFacts): string {
  return `${formatGib(facts.mem.swap_used_kb)} / ${formatGib(facts.mem.swap_total_kb)}`;
}

export function diskNote(disk: ServerDisk): string {
  return `${formatGib(disk.used_kb)} / ${formatGib(disk.size_kb)}`;
}

/** The filesystem under the most pressure — what a single meter should show. */
export function fullestDisk(facts: ServerFacts): ServerDisk | null {
  if (facts.disks.length === 0) return null;
  return facts.disks.reduce((worst, d) => (d.used_pct > worst.used_pct ? d : worst));
}

/** Model and core count, whichever of the two the host reported. */
export function cpuText(facts: ServerFacts): string {
  const cores = facts.cpu.cores === null ? '' : t('servers.cores', { n: String(facts.cpu.cores) });

  return [facts.cpu.model, cores].filter(Boolean).join(' · ');
}
