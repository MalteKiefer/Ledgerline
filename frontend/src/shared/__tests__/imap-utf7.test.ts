import { describe, it, expect } from 'vitest';
import { decodeImapFolder } from '../imap-utf7';

describe('decodeImapFolder', () => {
  it('decodes the real folder name a Gmail account reported', () => {
    // Straight from a live mailbox: this is why the fix exists.
    expect(decodeImapFolder('[Gmail].Entw&APw-rfe')).toBe('[Gmail].Entwürfe');
  });

  it('leaves plain names untouched', () => {
    expect(decodeImapFolder('INBOX')).toBe('INBOX');
    expect(decodeImapFolder('INBOX.Sent Items')).toBe('INBOX.Sent Items');
  });

  it('reads "&-" as a literal ampersand', () => {
    expect(decodeImapFolder('Bill &- Ted')).toBe('Bill & Ted');
  });

  it('uses the comma variant of base64, not the slash', () => {
    // U+263A: standard base64 would be "Jjo", IMAP's alphabet swaps / for ,
    expect(decodeImapFolder('&Jjo-')).toBe('☺');
  });

  it('handles several shifts in one name', () => {
    expect(decodeImapFolder('&APY-l/&APY-l')).toBe('öl/öl');
  });

  it('shows an unterminated shift as it is rather than guessing', () => {
    // Not a valid name; mangled is better than confidently wrong.
    expect(decodeImapFolder('Broken&APw')).toBe('Broken&APw');
  });

  it('shows an undecodable chunk verbatim', () => {
    expect(decodeImapFolder('Odd&!!!-name')).toBe('Odd&!!!-name');
  });
});
