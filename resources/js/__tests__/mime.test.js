import { describe, it, expect } from 'vitest';
import { decodeWords, splitMessage, hasAttachment, parseEnvelope, displayAddress, parseDate, parseMessage } from '../shared/mime.js';

describe('decodeWords (RFC 2047)', () => {
    it('decodes a UTF-8 Base64 encoded-word', () => {
        expect(decodeWords('=?UTF-8?B?w6TDtsO8?=')).toBe('äöü');
    });

    it('decodes a Q-encoded word with underscore-as-space', () => {
        expect(decodeWords('=?UTF-8?Q?Hello_=C3=A4?=')).toBe('Hello ä');
    });

    it('collapses whitespace between adjacent encoded-words', () => {
        expect(decodeWords('=?UTF-8?B?w6Q=?= =?UTF-8?B?w7Y=?=')).toBe('äö');
    });

    it('passes plain ASCII through unchanged', () => {
        expect(decodeWords('Just a subject')).toBe('Just a subject');
    });
});

describe('splitMessage', () => {
    it('splits headers from body and unfolds continuations', () => {
        const raw = 'Subject: a\r\n long subject\r\nFrom: x@y\r\n\r\nthe body';
        const { headers, body } = splitMessage(raw);
        expect(headers.subject).toBe('a long subject');
        expect(headers.from).toBe('x@y');
        expect(body).toBe('the body');
    });

    it('first occurrence of a header wins', () => {
        const { headers } = splitMessage('X: one\nX: two\n\nb');
        expect(headers.x).toBe('one');
    });
});

describe('hasAttachment', () => {
    it('true for multipart/mixed', () => {
        expect(hasAttachment({ 'content-type': 'multipart/mixed; boundary=x' }, '')).toBe(true);
    });

    it('true when a part is disposition attachment', () => {
        expect(hasAttachment({ 'content-type': 'multipart/related' }, 'Content-Disposition: attachment; filename="a.pdf"')).toBe(true);
    });

    it('false for a plain text mail', () => {
        expect(hasAttachment({ 'content-type': 'text/plain' }, 'hello')).toBe(false);
    });
});

describe('parseEnvelope', () => {
    it('returns decoded envelope fields', () => {
        const raw = 'From: =?UTF-8?B?w4TDlg==?= <a@b>\nTo: c@d\nSubject: =?UTF-8?Q?hi?=\nDate: Mon, 03 Aug 2026 10:00:00 +0000\nContent-Type: multipart/mixed; boundary=z\n\nx';
        const e = parseEnvelope(raw);
        expect(e.from).toBe('ÄÖ <a@b>');
        expect(e.to).toBe('c@d');
        expect(e.subject).toBe('hi');
        expect(e.hasAttachment).toBe(true);
    });
});

describe('parseMessage', () => {
    it('extracts text body and a base64 attachment from multipart/mixed', () => {
        // "hi" as base64 is "aGk=".
        const raw = [
            'Content-Type: multipart/mixed; boundary="B"',
            '',
            '--B',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'the body text',
            '--B',
            'Content-Type: application/pdf; name="doc.pdf"',
            'Content-Disposition: attachment; filename="doc.pdf"',
            'Content-Transfer-Encoding: base64',
            '',
            'aGk=',
            '--B--',
            '',
        ].join('\r\n');
        const msg = parseMessage(raw);
        expect(msg.textBody.trim()).toBe('the body text');
        expect(msg.attachments).toHaveLength(1);
        expect(msg.attachments[0].filename).toBe('doc.pdf');
        expect(msg.attachments[0].contentType).toBe('application/pdf');
        expect(new TextDecoder().decode(msg.attachments[0].bytes)).toBe('hi');
    });

    it('decodes a quoted-printable text body', () => {
        const raw = 'Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nGr=C3=BC=C3=9Fe';
        expect(parseMessage(raw).textBody).toBe('Grüße');
    });

    it('a plain single-part message has no attachments', () => {
        const msg = parseMessage('Subject: x\r\nContent-Type: text/plain\r\n\r\nhello');
        expect(msg.attachments).toHaveLength(0);
        expect(msg.textBody).toBe('hello');
    });
});

describe('displayAddress', () => {
    it('extracts the display name', () => {
        expect(displayAddress('Malte Kiefer <m@k.de>')).toBe('Malte Kiefer');
    });
    it('falls back to the address when no name', () => {
        expect(displayAddress('<m@k.de>')).toBe('m@k.de');
    });
    it('returns a bare address unchanged', () => {
        expect(displayAddress('m@k.de')).toBe('m@k.de');
    });
});

describe('parseDate', () => {
    it('parses an RFC 2822 date', () => {
        expect(parseDate('Mon, 03 Aug 2026 10:00:00 +0000')).toBeInstanceOf(Date);
    });
    it('returns null on garbage', () => {
        expect(parseDate('not a date')).toBeNull();
    });
});
