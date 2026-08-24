import { describe, expect, it } from 'vitest';
import {
  blankExpense, descendantIds, ledgerTotals, normaliseLedger, ownTotals,
  projectTree, rolledTotals, subtreeIds, type ProjectLike, type ReceiptLike, type TxLike,
} from '../finance-projects';

const P = (id: number, parent_id: number | null, name = `p${id}`, expenses: unknown = null): ProjectLike =>
  ({ id, parent_id, name, expenses });

describe('projectTree', () => {
  it('nests children under their parent, depth-first', () => {
    const rows = projectTree([P(1, null, 'house'), P(2, 1, 'cellar'), P(3, 2, 'pump'), P(4, null, 'garden')]);
    expect(rows.map((r) => [r.p.name, r.depth])).toEqual([
      ['house', 0], ['cellar', 1], ['pump', 2], ['garden', 0],
    ]);
  });

  it('surfaces a row with an unknown parent as a root instead of dropping it', () => {
    const rows = projectTree([P(2, 99, 'orphan')]);
    expect(rows).toEqual([{ p: rows[0].p, depth: 0 }]);
    expect(rows[0].p.name).toBe('orphan');
  });

  it('does not loop forever on a parent cycle', () => {
    // 1 -> 2 -> 1: neither is a root, so nothing is emitted, but it terminates.
    expect(projectTree([P(1, 2), P(2, 1)])).toEqual([]);
  });
});

describe('descendantIds / subtreeIds', () => {
  const tree = [P(1, null), P(2, 1), P(3, 2), P(4, null)];
  it('collects the whole branch below a project', () => {
    expect([...descendantIds(tree, 1)].sort()).toEqual([2, 3]);
    expect(descendantIds(tree, 4).size).toBe(0);
  });
  it('subtree includes the project itself', () => {
    expect([...subtreeIds(tree, 1)].sort()).toEqual([1, 2, 3]);
  });
});

describe('normaliseLedger', () => {
  it('defaults a legacy row without direction to an outflow', () => {
    const [row] = normaliseLedger([{ amount: 50, date: '2026-03-01', category: 'Material' }]);
    expect(row).toMatchObject({ direction: 'out', amount: 50, date: '2026-03-01', category: 'Material' });
    // A legacy row has no label/description — both read as null, not "undefined".
    expect(row.title).toBeNull();
    expect(row.note).toBeNull();
    expect(row.id).toBe('e0'); // positional fallback when the writer sent none
  });

  it('reads a negative amount as the opposite direction, keeping amount unsigned', () => {
    expect(normaliseLedger([{ amount: -50 }])[0]).toMatchObject({ direction: 'in', amount: 50 });
    expect(normaliseLedger([{ amount: -50, direction: 'in' }])[0]).toMatchObject({ direction: 'out', amount: 50 });
  });

  it('drops junk instead of poisoning a sum', () => {
    expect(normaliseLedger([null, 'x', { amount: 0 }, { amount: 'abc' }, {}])).toEqual([]);
    expect(normaliseLedger(undefined)).toEqual([]);
    expect(normaliseLedger({ amount: 5 })).toEqual([]);
  });

  it('keeps label and description as separate fields', () => {
    const [row] = normaliseLedger([{ amount: 250, title: 'Bagger', note: 'Miete 2 Tage inkl. Fahrer' }]);
    expect(row.title).toBe('Bagger');
    expect(row.note).toBe('Miete 2 Tage inkl. Fahrer');
    // An empty string is "not set", so the table shows the placeholder, not a blank cell.
    expect(normaliseLedger([{ amount: 1, title: '', note: '' }])[0]).toMatchObject({ title: null, note: null });
  });

  it('trims an ISO datetime down to the date input format', () => {
    expect(normaliseLedger([{ amount: 1, date: '2026-03-01T00:00:00.000000Z' }])[0].date).toBe('2026-03-01');
  });
});

describe('ledgerTotals', () => {
  it('nets credits against debits', () => {
    const rows = normaliseLedger([
      { amount: 250, direction: 'out' }, { amount: 19.99, direction: 'out' }, { amount: 70, direction: 'in' },
    ]);
    expect(ledgerTotals(rows)).toEqual({ out: 269.99, in: 70, net: 199.99 });
  });
});

describe('ownTotals / rolledTotals', () => {
  const projects = [
    P(1, null, 'house', [{ id: 'a', amount: 100, direction: 'out' }]),
    P(2, 1, 'cellar', [{ id: 'b', amount: 250, direction: 'out' }, { id: 'c', amount: 50, direction: 'in' }]),
  ];
  const txs: TxLike[] = [
    { id: 10, amount: -30, finance_project_id: 1 },   // spend booked on the parent
    { id: 11, amount: 5, finance_project_id: 2 },     // refund booked on the child
    { id: 12, amount: -999, finance_project_id: null }, // unassigned: must not count
  ];
  const receipts: ReceiptLike[] = [
    { id: 20, amount: 40, finance_project_id: 2, bank_transaction_id: null, linked_transaction_ids: null },
  ];

  it('counts only the rows and assignments of that one project', () => {
    expect(ownTotals(projects[0], txs, receipts)).toEqual({ out: 130, in: 0, net: 130 });
    expect(ownTotals(projects[1], txs, receipts)).toEqual({ out: 290, in: 55, net: 235 });
  });

  it('rolls the subtree up into the parent', () => {
    expect(rolledTotals(projects, 1, txs, receipts)).toEqual({ out: 420, in: 55, net: 365 });
    expect(rolledTotals(projects, 2, txs, receipts)).toEqual({ out: 290, in: 55, net: 235 });
  });

  it('does not double-count a receipt whose settling transaction is assigned here', () => {
    const settled: ReceiptLike[] = [
      { id: 21, amount: 30, finance_project_id: 1, bank_transaction_id: 10, linked_transaction_ids: null },
    ];
    // The 30 is already in via transaction 10 — the receipt is its document.
    expect(ownTotals(projects[0], txs, settled)).toEqual({ out: 130, in: 0, net: 130 });
  });

  it('still counts a receipt whose split transactions belong elsewhere', () => {
    const split: ReceiptLike[] = [
      { id: 22, amount: 60, finance_project_id: 1, bank_transaction_id: null, linked_transaction_ids: [12] },
    ];
    expect(ownTotals(projects[0], txs, split)).toEqual({ out: 190, in: 0, net: 190 });
  });
});

describe('blankExpense', () => {
  it('starts empty with the requested direction and a unique id', () => {
    const a = blankExpense('in', '2026-08-24');
    expect(a).toMatchObject({ direction: 'in', amount: 0, date: '2026-08-24', title: null, note: null, category: null });
    expect(blankExpense('out', '2026-08-24').id).not.toBe(a.id);
  });
});
