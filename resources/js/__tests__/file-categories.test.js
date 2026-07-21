import { describe, expect, it } from 'vitest';
import { categoryTint, fileTypeLabel, CATEGORY_TINT, FOLDER_TINT } from '../shared/file-categories';

describe('categoryTint', () => {
  it('returns the iOS hex per category (extension wins)', () => {
    expect(categoryTint('report.pdf', '')).toBe('#e5544b');       // PDF
    expect(categoryTint('sheet.xlsx', '')).toBe('#59ad6b');       // SPREADSHEET
    expect(categoryTint('pic.png', '')).toBe('#9e70fa');          // IMAGE
    expect(categoryTint('clip.mov', '')).toBe('#e5679e');         // VIDEO
    expect(categoryTint('app.js', '')).toBe('#6b7280');           // CODE → grey
  });
  it('falls back to MIME then OTHER', () => {
    expect(categoryTint('noext', 'application/pdf')).toBe('#e5544b');
    expect(categoryTint('mystery', '')).toBe(CATEGORY_TINT.OTHER);
  });
  it('folder tint is the iOS blue', () => {
    expect(FOLDER_TINT).toBe('#3b9fd6');
  });
});

describe('fileTypeLabel', () => {
  it('returns the specific label key when the extension has one', () => {
    expect(fileTypeLabel('a.pdf', '')).toBe('filetype.pdf');
    expect(fileTypeLabel('a.docx', '')).toBe('filetype.word');
    expect(fileTypeLabel('a.xlsx', '')).toBe('filetype.excel');
    expect(fileTypeLabel('a.js', '')).toBe('filetype.javascript');
    expect(fileTypeLabel('a.mov', '')).toBe('filetype.quicktime');
  });
  it('falls back to the category token when no specific label', () => {
    expect(fileTypeLabel('a.bin', '')).toBe('filetype.other');
    expect(fileTypeLabel('a.rb', '')).toBe('filetype.code'); // rb = CODE, no specific label
  });
});
