import { describe, it, expect } from 'vitest';
import { receiptAmount, projectChildren, descendantIds, ownTotal, rolledTotal, projectTree } from '../shared/finance-projects.js';

const PROJECTS = [
    { id: 'haus', name: 'Haus', parentId: null, expenses: [] },
    { id: 'keller', name: 'Keller abdichten', parentId: 'haus', expenses: [{ id: 'e1', amount: 250, note: 'Bagger Hand' }] },
    { id: 'dach', name: 'Dach', parentId: 'haus', expenses: [{ id: 'e2', amount: 100 }] },
];
// receipts as {r, tx}
const RECEIPTS = [
    { r: { projectId: 'keller', total: 80 }, tx: { amount: -80 } },
    { r: { projectId: 'haus' }, tx: { amount: -500 } }, // no total → uses booking amount
    { r: { projectId: null }, tx: { amount: -9 } },
];

describe('finance projects', () => {
    it('takes the receipt gross, else the booking amount', () => {
        expect(receiptAmount({ total: 80 }, { amount: -80 })).toBe(80);
        expect(receiptAmount({}, { amount: -500 })).toBe(500);
    });
    it('walks the tree', () => {
        expect(projectChildren(PROJECTS, 'haus').map((p) => p.id).sort()).toEqual(['dach', 'keller']);
        expect(descendantIds(PROJECTS, 'haus').sort()).toEqual(['dach', 'keller']);
        expect(projectChildren(PROJECTS, null).map((p) => p.id)).toEqual(['haus']);
    });
    it('sums own vs rolled-up totals', () => {
        expect(ownTotal(PROJECTS[1], RECEIPTS)).toBe(330); // 250 expense + 80 receipt
        expect(ownTotal(PROJECTS[0], RECEIPTS)).toBe(500); // Haus own = its 500 receipt
        expect(rolledTotal(PROJECTS, 'haus', RECEIPTS)).toBe(930); // 500 + (250+80) + 100
        expect(rolledTotal(PROJECTS, 'keller', RECEIPTS)).toBe(330);
    });
    it('flattens depth-first with depth', () => {
        const t = projectTree(PROJECTS);
        expect(t.map((x) => [x.project.id, x.depth])).toEqual([['haus', 0], ['dach', 1], ['keller', 1]]);
    });
    it('surfaces an orphan (missing parent) at the root', () => {
        const t = projectTree([{ id: 'x', name: 'X', parentId: 'gone' }]);
        expect(t).toEqual([{ project: { id: 'x', name: 'X', parentId: 'gone' }, depth: 0 }]);
    });
});
