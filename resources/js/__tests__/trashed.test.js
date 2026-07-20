import { describe, it, expect } from 'vitest';

// Test the _isTrashed logic inline (mirrors what zk-module provides)
function isTrashed(x) {
    return x.trashed === true || (typeof x.trashed === 'string' && x.trashed.length > 0);
}

describe('isTrashed (legacy boolean + ISO string)', () => {
    it('boolean true is trashed (legacy)', () => {
        expect(isTrashed({ trashed: true })).toBe(true);
    });
    it('ISO timestamp string is trashed (new format)', () => {
        expect(isTrashed({ trashed: '2024-01-15T10:30:00.000Z' })).toBe(true);
    });
    it('boolean false is not trashed', () => {
        expect(isTrashed({ trashed: false })).toBe(false);
    });
    it('undefined trashed is not trashed', () => {
        expect(isTrashed({ trashed: undefined })).toBe(false);
    });
    it('null trashed is not trashed', () => {
        expect(isTrashed({ trashed: null })).toBe(false);
    });
    it('empty string is not trashed', () => {
        expect(isTrashed({ trashed: '' })).toBe(false);
    });
});
