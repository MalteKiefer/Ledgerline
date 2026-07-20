import { describe, it, expect } from 'vitest';
import { zkModule } from '../shared/zk-module';

// Exercise the REAL _isTrashed from zkModule (not an inline copy) so the test
// tracks the shipped implementation.
const { _isTrashed } = zkModule({ map: {} });
const isTrashed = (x) => _isTrashed(x);

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
