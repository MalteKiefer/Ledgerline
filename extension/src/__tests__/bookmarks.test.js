import { describe, it, expect } from 'vitest';
import {
  newId, isTrashed, isSafeUrl, listBookmarks, getBookmark,
  addBookmark, updateBookmark, trashBookmark, restoreBookmark,
  createFolder, renameFolder, deleteFolder,
} from '../bookmarks.js';

describe('newId', () => {
  it('is 32 hex chars and unique', () => {
    const a = newId(), b = newId();
    expect(a).toMatch(/^[0-9a-f]{32}$/);
    expect(a).not.toBe(b);
  });
});

describe('isTrashed', () => {
  it('true for boolean true (legacy) and ISO string, false otherwise', () => {
    expect(isTrashed({ trashed: true })).toBe(true);
    expect(isTrashed({ trashed: '2026-07-20T00:00:00.000Z' })).toBe(true);
    expect(isTrashed({ trashed: false })).toBe(false);
    expect(isTrashed({ trashed: '' })).toBe(false);
    expect(isTrashed({})).toBe(false);
  });
});

describe('isSafeUrl', () => {
  it('allows http(s), rejects javascript/data/other', () => {
    expect(isSafeUrl('https://example.com')).toBe(true);
    expect(isSafeUrl('http://example.com/x')).toBe(true);
    expect(isSafeUrl('javascript:alert(1)')).toBe(false);
    expect(isSafeUrl('data:text/html,x')).toBe(false);
    expect(isSafeUrl('ftp://x')).toBe(false);
    expect(isSafeUrl('not a url')).toBe(false);
    expect(isSafeUrl(null)).toBe(false);
  });
});

describe('bookmark CRUD preserves other manifest modules', () => {
  function fixture() {
    return {
      secrets: [{ id: 's1', type: 'login' }],
      secretFolders: [{ id: 'sf1', name: 'Private' }],
      notes: [{ id: 'n1' }],
      todos: [{ id: 't1' }],
      bookmarks: [{ id: 'b1', title: 'Old', url: 'https://old.test', trashed: '2026-01-01T00:00:00.000Z' }],
      bookmarkFolders: [{ id: 'f1', name: 'Dev', parentId: null }],
    };
  }

  it('addBookmark unshifts and leaves other modules byte-identical', () => {
    const m = fixture();
    const before = JSON.stringify({ secrets: m.secrets, notes: m.notes, todos: m.todos, secretFolders: m.secretFolders });
    const { id } = addBookmark(m, { title: 'New', url: 'https://new.test', tags: ['a'] });
    expect(m.bookmarks[0].id).toBe(id);
    expect(m.bookmarks[0].title).toBe('New');
    expect(JSON.stringify({ secrets: m.secrets, notes: m.notes, todos: m.todos, secretFolders: m.secretFolders })).toBe(before);
  });

  it('listBookmarks returns only non-trashed', () => {
    const m = fixture();
    addBookmark(m, { title: 'Live', url: 'https://live.test' });
    const { bookmarks, folders } = listBookmarks(m);
    expect(bookmarks.map((b) => b.title)).toEqual(['Live']); // b1 is trashed
    expect(folders).toHaveLength(1);
  });

  it('updateBookmark merges only allowed fields', () => {
    const m = fixture();
    updateBookmark(m, 'b1', { title: 'Renamed', bogus: 'x', favorite: true });
    expect(m.bookmarks[0].title).toBe('Renamed');
    expect(m.bookmarks[0].favorite).toBe(true);
    expect(m.bookmarks[0].bogus).toBeUndefined();
  });

  it('trash sets ISO, restore removes it', () => {
    const m = fixture();
    const { id } = addBookmark(m, { title: 'X', url: 'https://x.test' });
    trashBookmark(m, id, '2026-07-20T10:00:00.000Z');
    expect(getBookmark(m, id).trashed).toBe('2026-07-20T10:00:00.000Z');
    restoreBookmark(m, id);
    expect('trashed' in getBookmark(m, id)).toBe(false);
  });

  it('title/url/tags are capped', () => {
    const m = fixture();
    const { id } = addBookmark(m, { title: 'x'.repeat(999), url: 'https://e.test', tags: Array(99).fill('t') });
    const b = getBookmark(m, id);
    expect(b.title.length).toBe(500);
    expect(b.tags.length).toBe(50);
  });
});

describe('folders', () => {
  it('deleteFolder reparents children to root and clears folderId on bookmarks', () => {
    const m = { bookmarkFolders: [{ id: 'p', name: 'P', parentId: null }, { id: 'c', name: 'C', parentId: 'p' }], bookmarks: [{ id: 'b', folderId: 'p' }] };
    deleteFolder(m, 'p');
    expect(m.bookmarkFolders.find((f) => f.id === 'c').parentId).toBeNull();
    expect(m.bookmarkFolders.find((f) => f.id === 'p')).toBeUndefined();
    expect(m.bookmarks[0].folderId).toBeNull();
  });

  it('createFolder + renameFolder', () => {
    const m = {};
    const { id } = createFolder(m, { name: 'New', parentId: null });
    expect(m.bookmarkFolders[0].id).toBe(id);
    renameFolder(m, id, 'Renamed');
    expect(m.bookmarkFolders[0].name).toBe('Renamed');
  });
});
