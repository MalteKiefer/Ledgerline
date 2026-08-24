/**
 * Cost-project maths — the project ledger and its roll-up over the subtree.
 *
 * A project earns money two ways: rows the owner types by hand (`expenses` on
 * the project row, a free-form JSON array on the backend) and real bookings
 * assigned to it (`finance_project_id` on a bank transaction or a receipt).
 *
 * The hand-typed row shape is OUR contract (the column is `additionalProperties`
 * on the wire) and is documented in openapi.yaml as `ProjectExpense`:
 *
 *   { id, direction: 'out' | 'in', amount > 0, date, title, note,
 *     category, payment_method_id }
 *
 * `title` is the short label ("Bagger"), `note` the longer description — two
 * fields, because one free-text column forces the owner to cram both into a
 * table cell.
 *
 * `direction` is what makes a Zubuchung expressible at all. Legacy rows written
 * before it existed carry no `direction` and are read as 'out' — which is
 * exactly how the server's EÜR treated every row back then (`abs(amount)`), so
 * old data keeps its old meaning instead of silently flipping sign.
 */

export interface ProjectExpense {
  id: string;
  direction: 'out' | 'in';
  amount: number;
  date: string | null;
  /** Short label shown in the ledger table (Bezeichnung). */
  title: string | null;
  /** Longer free text (Beschreibung). */
  note: string | null;
  category: string | null;
  payment_method_id: number | null;
}

/** Only what the tree/roll-up needs — the real store type is a superset. */
export interface ProjectLike {
  id: number;
  parent_id: number | null;
  name: string;
  expenses?: unknown;
}

export interface TxLike {
  id: number;
  amount: number;
  finance_project_id: number | null;
}

export interface ReceiptLike {
  id: number;
  amount: number | string | null;
  finance_project_id: number | null;
  bank_transaction_id: number | null;
  linked_transaction_ids: number[] | null;
}

/** out = money spent, in = money credited back, net = out − in (the cost). */
export interface Totals { out: number; in: number; net: number }

function r2(n: number): number { return Math.round((Number.isFinite(n) ? n : 0) * 100) / 100; }

function totals(out: number, inn: number): Totals {
  return { out: r2(out), in: r2(inn), net: r2(out - inn) };
}

/**
 * Indented depth-first tree. A row whose `parent_id` points at a project we do
 * not have (deleted, or someone else's) surfaces as a root rather than
 * vanishing, and a parent cycle is broken by the visited set — the backend
 * guards its move endpoint, but a hand-written PUT could still get one in.
 */
export function projectTree<T extends ProjectLike>(projects: T[]): { p: T; depth: number }[] {
  const ids = new Set(projects.map((p) => p.id));
  const byParent = new Map<number | null, T[]>();
  for (const p of projects) {
    const key = p.parent_id != null && ids.has(p.parent_id) ? p.parent_id : null;
    const list = byParent.get(key);
    if (list) list.push(p); else byParent.set(key, [p]);
  }
  const out: { p: T; depth: number }[] = [];
  const seen = new Set<number>();
  const walk = (parent: number | null, depth: number): void => {
    for (const p of byParent.get(parent) ?? []) {
      if (seen.has(p.id)) continue;
      seen.add(p.id);
      out.push({ p, depth });
      walk(p.id, depth + 1);
    }
  };
  walk(null, 0);
  return out;
}

/** Every project below `id` (excluding `id` itself). */
export function descendantIds(projects: ProjectLike[], id: number): Set<number> {
  const byParent = new Map<number, number[]>();
  for (const p of projects) {
    if (p.parent_id == null) continue;
    const list = byParent.get(p.parent_id);
    if (list) list.push(p.id); else byParent.set(p.parent_id, [p.id]);
  }
  const out = new Set<number>();
  const stack = [...(byParent.get(id) ?? [])];
  while (stack.length) {
    const cur = stack.pop() as number;
    if (out.has(cur) || cur === id) continue;
    out.add(cur);
    stack.push(...(byParent.get(cur) ?? []));
  }
  return out;
}

/** The project itself plus everything below it. */
export function subtreeIds(projects: ProjectLike[], id: number): Set<number> {
  const ids = descendantIds(projects, id);
  ids.add(id);
  return ids;
}

/**
 * Read the stored JSON into typed rows, tolerating anything another client
 * wrote: a missing id gets a positional fallback, a negative amount is folded
 * into its direction (so `-50` reads as a 50 credit rather than a negative
 * cost), and a row with no usable amount is dropped instead of poisoning a sum.
 */
export function normaliseLedger(expenses: unknown): ProjectExpense[] {
  if (!Array.isArray(expenses)) return [];
  const out: ProjectExpense[] = [];
  expenses.forEach((raw, i) => {
    if (!raw || typeof raw !== 'object') return;
    const row = raw as Record<string, unknown>;
    const amount = Number(row.amount);
    if (!Number.isFinite(amount) || amount === 0) return;
    const declared = row.direction === 'in' ? 'in' : 'out';
    // A negative amount flips the declared direction — the sign is the more
    // explicit statement, and it keeps `amount` unsigned as documented.
    const direction: 'out' | 'in' = amount < 0 ? (declared === 'in' ? 'out' : 'in') : declared;
    out.push({
      id: typeof row.id === 'string' && row.id !== '' ? row.id : `e${i}`,
      direction,
      amount: r2(Math.abs(amount)),
      date: typeof row.date === 'string' && row.date !== '' ? row.date.slice(0, 10) : null,
      title: typeof row.title === 'string' && row.title !== '' ? row.title : null,
      category: typeof row.category === 'string' && row.category !== '' ? row.category : null,
      payment_method_id: Number.isFinite(Number(row.payment_method_id)) && row.payment_method_id != null
        ? Number(row.payment_method_id)
        : null,
      note: typeof row.note === 'string' && row.note !== '' ? row.note : null,
    });
  });
  return out;
}

export function ledgerTotals(rows: ProjectExpense[]): Totals {
  let out = 0;
  let inn = 0;
  for (const r of rows) (r.direction === 'in' ? (inn += r.amount) : (out += r.amount));
  return totals(out, inn);
}

/**
 * What the given projects cost on their own: hand-typed rows + assigned bank
 * transactions + assigned receipts.
 *
 * A receipt whose settling transaction is ALSO assigned here is skipped — the
 * receipt is the document for that booking, and counting both would double the
 * cost. That is the one case where this is more than a sum.
 */
export function totalsFor(
  projects: ProjectLike[],
  ids: Set<number>,
  txs: TxLike[],
  receipts: ReceiptLike[],
): Totals {
  let out = 0;
  let inn = 0;

  for (const p of projects) {
    if (!ids.has(p.id)) continue;
    const t = ledgerTotals(normaliseLedger(p.expenses));
    out += t.out;
    inn += t.in;
  }

  const countedTx = new Set<number>();
  for (const tx of txs) {
    if (tx.finance_project_id == null || !ids.has(tx.finance_project_id)) continue;
    countedTx.add(tx.id);
    const amount = Number(tx.amount) || 0;
    if (amount < 0) out += Math.abs(amount); else inn += amount;
  }

  for (const r of receipts) {
    if (r.finance_project_id == null || !ids.has(r.finance_project_id)) continue;
    const settled = [r.bank_transaction_id, ...(r.linked_transaction_ids ?? [])]
      .filter((id): id is number => id != null);
    if (settled.some((id) => countedTx.has(id))) continue;
    const amount = Math.abs(Number(r.amount) || 0);
    if (amount) out += amount;
  }

  return totals(out, inn);
}

/** Totals of one project alone (its own rows and its own assignments). */
export function ownTotals(project: ProjectLike, txs: TxLike[], receipts: ReceiptLike[]): Totals {
  return totalsFor([project], new Set([project.id]), txs, receipts);
}

/** Totals of one project including every subproject below it. */
export function rolledTotals(
  projects: ProjectLike[],
  id: number,
  txs: TxLike[],
  receipts: ReceiptLike[],
): Totals {
  return totalsFor(projects, subtreeIds(projects, id), txs, receipts);
}

/** A fresh hand-typed row (id is client-side; the server stores the array as-is). */
export function blankExpense(direction: 'out' | 'in', date: string): ProjectExpense {
  return {
    id: `x${Date.now().toString(36)}${Math.floor(Math.random() * 1e6).toString(36)}`,
    direction,
    amount: 0,
    date,
    title: null,
    note: null,
    category: null,
    payment_method_id: null,
  };
}
