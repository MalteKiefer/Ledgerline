import { describe, it, expect } from 'vitest';
import { jsonClone } from '../shared/clone';

describe('jsonClone', () => {
    it('deep-clones plain JSON data by value', () => {
        const src = { v: 3, todos: [{ id: 'a', title: 'x', tags: ['t'] }], todoLists: [] };
        const out = jsonClone(src);
        expect(out).toEqual(src);
        expect(out).not.toBe(src);
        expect(out.todos).not.toBe(src.todos);
        expect(out.todos[0]).not.toBe(src.todos[0]);
    });

    it('passes null/undefined through', () => {
        expect(jsonClone(null)).toBe(null);
        expect(jsonClone(undefined)).toBe(undefined);
    });

    it('survives a Proxy-wrapped record that structuredClone rejects', () => {
        // Models the Alpine reactive Proxy that made flush() throw a DataCloneError and
        // silently drop a created todo. structuredClone rejects such proxies; jsonClone
        // goes through the get-traps and clones the enumerable data.
        const wrap = (o) => new Proxy(o, {});
        const reactive = wrap({ v: 3, todos: [wrap({ id: 'a', title: 't', done: false })], todoLists: [] });

        // A revoked/exotic proxy is exactly what structuredClone chokes on; assert our
        // clone still produces the plain data.
        const out = jsonClone(reactive);
        expect(out).toEqual({ v: 3, todos: [{ id: 'a', title: 't', done: false }], todoLists: [] });
        // The result is a plain object, not a proxy.
        expect(Array.isArray(out.todos)).toBe(true);
    });
});
