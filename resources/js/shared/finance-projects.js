// Cost projects (client-side, ZK). A project bundles receipts and manual "hand" expenses
// and can nest sub-projects (parentId). Totals roll up through the tree. Pure + testable.
// Project shape: { id, name, parentId|null, note, expenses:[{id, amount, date, note}] }.

/** The amount a bundled receipt contributes: its recognised gross, else the booking amount. */
export function receiptAmount(r, tx) {
    const t = Number(r && r.total);
    if (Number.isFinite(t) && t > 0) return t;
    return Math.abs(Number(tx && tx.amount) || 0);
}

/** Direct children of a project id (null = root level). */
export function projectChildren(projects, parentId) {
    const pid = parentId || null;
    return (projects || []).filter((p) => (p.parentId || null) === pid);
}

/** All descendant ids of a project (excludes itself), cycle-safe. */
export function descendantIds(projects, id) {
    const out = [];
    const seen = new Set();
    const walk = (pid) => {
        for (const c of projectChildren(projects, pid)) {
            if (seen.has(c.id)) continue;
            seen.add(c.id);
            out.push(c.id);
            walk(c.id);
        }
    };
    walk(id);
    return out;
}

/** Sum of a project's own manual expenses. */
export function expensesSum(project) {
    return (project && project.expenses || []).reduce((s, e) => s + (Number(e.amount) || 0), 0);
}

/** The {r, tx} receipts assigned directly to a project id. */
export function projectReceipts(receipts, id) {
    return (receipts || []).filter((x) => x.r && x.r.projectId === id);
}

/** A project's own total (manual expenses + directly-assigned receipts, no descendants). */
export function ownTotal(project, receipts) {
    const rs = projectReceipts(receipts, project.id).reduce((s, x) => s + receiptAmount(x.r, x.tx), 0);
    return expensesSum(project) + rs;
}

/** A project's rolled-up total, including every descendant. Rounded to the cent. */
export function rolledTotal(projects, id, receipts) {
    const self = (projects || []).find((p) => p.id === id);
    if (! self) return 0;
    let t = ownTotal(self, receipts);
    for (const did of descendantIds(projects, id)) {
        const d = projects.find((p) => p.id === did);
        if (d) t += ownTotal(d, receipts);
    }
    return Math.round(t * 100) / 100;
}

/**
 * Flattened tree for rendering: `[{ project, depth }]`, depth-first, parents before
 * children, alphabetical within a level. Cycle-safe; a project whose parent is missing
 * surfaces at the root so it is never hidden.
 */
export function projectTree(projects) {
    const out = [];
    const seen = new Set();
    const sortLvl = (arr) => [...arr].sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
    const walk = (parentId, depth) => {
        for (const p of sortLvl(projectChildren(projects, parentId))) {
            if (seen.has(p.id)) continue;
            seen.add(p.id);
            out.push({ project: p, depth });
            walk(p.id, depth + 1);
        }
    };
    walk(null, 0);
    for (const p of (projects || [])) if (! seen.has(p.id)) { out.push({ project: p, depth: 0 }); seen.add(p.id); }
    return out;
}
