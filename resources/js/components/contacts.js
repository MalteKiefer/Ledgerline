// Contacts module (vCard 4.0, ZK). Extracted from app.js.
import { getJson, postForm } from '../shared/api';
import { fetchDecrypt, queueBlobDelete } from '../shared/blob-io';
import { padBlob } from '../shared/padme';
import { loadLeaflet } from '../shared/lazy-loaders';
import { zkModule, bootGalleryStore } from '../shared/zk-module';
import { contactNameParts, contactDisplayName } from '../shared/contact-utils';

/**
 * vCard 4.0 read/write, entirely in the browser (the server never sees a
 * plaintext vCard — zero-knowledge). Maps our contact record to/from the RFC
 * 6350 properties; PHOTO is a base64 data URI (decoded from / encoded to the
 * encrypted avatar blob by the caller). Lines are folded at 75 octets on write
 * and unfolded on read.
 */
const VCard = {
    esc(v) { return String(v ?? '').replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;'); },
    unesc(v) { return String(v ?? '').replace(/\\n/gi, '\n').replace(/\\,/g, ',').replace(/\\;/g, ';').replace(/\\\\/g, '\\'); },
    fold(line) {
        // RFC 6350 §3.2: fold at 75 OCTETS (UTF-8), never mid-multibyte-char, with
        // each continuation line starting with a single space (counted in its 75).
        // Iterating with for..of yields whole code points, so a multibyte name
        // (umlauts etc.) is never split into a broken continuation.
        const enc = new TextEncoder();
        if (enc.encode(line).length <= 75) return line;
        const out = [];
        let cur = '', bytes = 0;
        for (const ch of line) {
            const b = enc.encode(ch).length;
            if (bytes + b > 75) { out.push(cur); cur = ' '; bytes = 1; }
            cur += ch; bytes += b;
        }
        out.push(cur);
        return out.join('\r\n');
    },

    _date(d) { return String(d || '').replace(/-/g, ''); },
    _fromDate(v) { const s = String(v).replace(/[^0-9]/g, ''); return s.length >= 8 ? `${s.slice(0, 4)}-${s.slice(4, 6)}-${s.slice(6, 8)}` : ''; },
    // Some exporters cram the whole address into the street subfield with
    // newline separators, leaving city/zip/country empty (shows as one run in a
    // single-line input). Split it back out heuristically.
    normAddr(a) {
        if (! a || ! /[\r\n]/.test(a.street || '')) return a;
        const lines = String(a.street).split(/[\r\n]+/).map((s) => s.trim()).filter(Boolean);
        const out = { ...a, street: '', city: a.city || '', zip: a.zip || '', region: a.region || '', country: a.country || '' };
        out.street = lines.shift() || '';
        for (const ln of lines) {
            const m = ln.match(/^(\d{4,6})\s*(.*)$/); // "79364" or "79364 Town"
            if (m && ! out.zip) { out.zip = m[1]; if (m[2] && ! out.city) out.city = m[2]; continue; }
            if (! out.city) { out.city = ln; continue; }
            if (! out.country) { out.country = ln; continue; }
            out.street += '\n' + ln;
        }
        return out;
    },
    // Reduce a vCard TYPE list (e.g. "cell,voice,pref") to one known label.
    normType(raw, fallback) {
        const toks = String(raw || '').toLowerCase().split(/[,;]/).map((s) => s.trim());
        if (toks.some((t) => t === 'cell' || t === 'mobile')) return 'cell';
        if (toks.includes('work')) return 'work';
        if (toks.includes('home')) return 'home';
        return fallback || 'other';
    },

    // Build a vCard 4.0 string for one contact. `photo` is an optional base64
    // data URI. Unknown properties captured on import (c._x) are re-emitted, so a
    // round-trip preserves fields we don't model.
    build(c, photo) {
        const L = [];
        const add = (line) => L.push(this.fold(line));
        add('BEGIN:VCARD');
        add('VERSION:4.0');
        if (c.uid) add('UID:' + c.uid);
        add('FN:' + this.esc(c.fn || [c.prefix, c.first, c.middle, c.last, c.suffix].filter(Boolean).join(' ')));
        add('N:' + [c.last, c.first, c.middle, c.prefix, c.suffix].map((x) => this.esc(x)).join(';'));
        if (c.nickname) add('NICKNAME:' + this.esc(c.nickname));
        if (c.org || c.department) add('ORG:' + this.esc(c.org) + (c.department ? ';' + this.esc(c.department) : ''));
        if (c.title) add('TITLE:' + this.esc(c.title));
        if (c.role) add('ROLE:' + this.esc(c.role));
        // No standard vCard property for a VAT id; use an RFC 6350 x-name.
        if (c.vatId) add('X-VAT-ID:' + this.esc(c.vatId));
        for (const e of (c.emails ?? [])) if (e.value) add('EMAIL;TYPE=' + (e.type || 'home') + ':' + this.esc(e.value));
        for (const p of (c.phones ?? [])) if (p.value) add('TEL;TYPE=' + (p.type || 'cell') + ':' + this.esc(p.value));
        for (const m of (c.impp ?? [])) if (m.value) add('IMPP;TYPE=' + (m.type || 'home') + ':' + this.esc(m.value));
        for (const a of (c.addresses ?? [])) {
            add('ADR;TYPE=' + (a.type || 'home') + ':;;' + this.esc(a.street) + ';' + this.esc(a.city) + ';' + this.esc(a.region) + ';' + this.esc(a.zip) + ';' + this.esc(a.country));
        }
        for (const u of (c.urls ?? [])) if (u.value) add('URL:' + this.esc(u.value));
        if (c.bday) add('BDAY:' + this._date(c.bday));
        if (c.anniversary) add('ANNIVERSARY:' + this._date(c.anniversary));
        if (c.note) add('NOTE:' + this.esc(c.note));
        if ((c.categories ?? []).length) add('CATEGORIES:' + c.categories.map((g) => this.esc(g)).join(','));
        if (photo) add('PHOTO:' + photo);
        for (const x of (c._x ?? [])) add(x); // pass-through unknown properties
        add('END:VCARD');
        return L.join('\r\n') + '\r\n';
    },

    // Parse a vCard file into an array of { contact, photo } (photo = data URI).
    parse(text) {
        const cards = [];
        // RFC 6350 unfolding: a continuation line begins with a space or tab.
        const rfc = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').replace(/\n[ \t]/g, '');
        const lines = rfc.split('\n');
        let cur = null;
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            if (! line.trim()) continue;
            const up = line.toUpperCase();
            if (up === 'BEGIN:VCARD') { cur = { c: this._blank(), photo: null }; continue; }
            if (up === 'END:VCARD') { if (cur) cards.push(cur); cur = null; continue; }
            if (! cur) continue;
            // Split on the first colon OUTSIDE double-quotes: a quoted parameter
            // value may legitimately contain a colon (e.g. ADR;LABEL="a:b":...).
            const idx = this._vColon(line);
            if (idx < 0) continue;
            const left = line.slice(0, idx);
            let value = line.slice(idx + 1);
            const [nameRaw, ...paramParts] = left.split(';');
            // Strip any Apple-style group prefix (item1.EMAIL → EMAIL).
            const name = nameRaw.split('.').pop().toUpperCase();
            const params = {};
            for (const p of paramParts) {
                const eq = p.indexOf('=');
                if (eq < 0) { params[p.toUpperCase()] = true; continue; } // bare param (vCard 2.1)
                params[p.slice(0, eq).toUpperCase()] = p.slice(eq + 1).replace(/^"(.*)"$/, '$1').toUpperCase();
            }
            const enc = typeof params.ENCODING === 'string' ? params.ENCODING : '';
            // QUOTED-PRINTABLE (vCard 2.1): join soft line breaks (trailing '=')
            // then decode, honouring CHARSET. Only QP-encoded properties consume
            // following physical lines, so folded base64 is unaffected.
            if (enc === 'QUOTED-PRINTABLE') {
                while (value.endsWith('=') && i + 1 < lines.length) { value = value.slice(0, -1) + lines[++i]; }
                value = this._qpDecode(value, typeof params.CHARSET === 'string' ? params.CHARSET : 'UTF-8');
            }
            const type = (typeof params.TYPE === 'string' ? params.TYPE : '').toLowerCase();
            const c = cur.c;
            switch (name) {
                case 'UID': c.uid = value; break;
                case 'FN': c.fn = this.unesc(value); break;
                case 'N': { const f = value.split(';').map((x) => this.unesc(x)); c.last = f[0] || ''; c.first = f[1] || ''; c.middle = f[2] || ''; c.prefix = f[3] || ''; c.suffix = f[4] || ''; break; }
                case 'NICKNAME': c.nickname = this.unesc(value); break;
                case 'ORG': { const o = value.split(';').map((x) => this.unesc(x)); c.org = o[0] || ''; c.department = o.slice(1).filter(Boolean).join(', '); break; }
                case 'TITLE': c.title = this.unesc(value); break;
                case 'ROLE': c.role = this.unesc(value); break;
                case 'EMAIL': c.emails.push({ value: this.unesc(value), type: this.normType(type, 'home') }); break;
                case 'TEL': c.phones.push({ value: this.unesc(value), type: this.normType(type, 'cell') }); break;
                case 'IMPP': c.impp.push({ value: this.unesc(value), type: this.normType(type, 'home') }); break;
                case 'URL': c.urls.push({ value: this.unesc(value), type: this.normType(type, 'home') }); break;
                case 'ADR': { const f = value.split(';').map((x) => this.unesc(x)); c.addresses.push(this.normAddr({ street: f[2] || '', city: f[3] || '', region: f[4] || '', zip: f[5] || '', country: f[6] || '', type: this.normType(type, 'home') })); break; }
                case 'BDAY': c.bday = this._fromDate(value); break;
                case 'ANNIVERSARY': c.anniversary = this._fromDate(value); break;
                case 'NOTE': c.note = this.unesc(value); break;
                case 'CATEGORIES': c.categories = value.split(',').map((x) => this.unesc(x.trim())).filter(Boolean); break;
                case 'X-VAT-ID': case 'X-VAT': case 'X-VATIN': c.vatId = this.unesc(value); break;
                // Accept an inline data: URI (vCard 4.0) OR base64-encoded photo
                // (vCard 3.0 / CardDAV: PHOTO;ENCODING=b;TYPE=JPEG:...). Either way
                // the image is re-encoded via canvas downstream, neutralising it.
                case 'PHOTO': cur.photo = this._vPhoto(value, enc, typeof params.TYPE === 'string' ? params.TYPE : ''); break;
                case 'VERSION': case 'PRODID': case 'REV': break; // regenerated on export
                default: c._x.push(line); // preserve anything we don't model
            }
        }
        return cards;
    },

    // Index of the first colon outside a double-quoted parameter value, or -1.
    _vColon(line) {
        let quoted = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') quoted = ! quoted;
            else if (ch === ':' && ! quoted) return i;
        }
        return -1;
    },

    // Decode a quoted-printable value to text, collecting bytes first so a
    // multi-byte UTF-8 (or other CHARSET) sequence decodes correctly.
    _qpDecode(s, charset) {
        const bytes = [];
        for (let i = 0; i < s.length; i++) {
            if (s[i] === '=' && i + 2 < s.length && /[0-9A-Fa-f]{2}/.test(s.substr(i + 1, 2))) {
                bytes.push(parseInt(s.substr(i + 1, 2), 16)); i += 2;
            } else { bytes.push(s.charCodeAt(i) & 0xff); }
        }
        try { return new TextDecoder(charset || 'utf-8').decode(new Uint8Array(bytes)); } catch { return s; }
    },

    // Normalise a PHOTO value to a data: URI (or null): pass through a data: URI,
    // else wrap a base64 payload (ENCODING=b/BASE64) using its TYPE as the mime.
    _vPhoto(value, enc, type) {
        if (value.startsWith('data:')) return value;
        if (enc === 'B' || enc === 'BASE64') {
            const t = (type || 'JPEG').toLowerCase();
            const mime = t.includes('/') ? t : 'image/' + (t === 'jpg' ? 'jpeg' : t);
            return 'data:' + mime + ';base64,' + value.replace(/\s+/g, '');
        }
        return null;
    },
    _blank() { return { fn: '', first: '', last: '', middle: '', prefix: '', suffix: '', nickname: '', org: '', department: '', title: '', role: '', vatId: '', emails: [], phones: [], impp: [], addresses: [], urls: [], bday: '', anniversary: '', note: '', categories: [], _x: [] }; },
};

// Dedupe the gallery-link reconcile across component mounts.
let _contactsReconAt = 0;

export default (config = {}, labels = {}) => ({
    ...zkModule({ store: 'contacts', map: { contacts: 'contacts' }, onLock: (self) => { self.currentId = null; self._revokeAvatars(); } }),
    contacts: [],
    currentId: null,
    editing: false, // detail pane opens read-only; edit via a button
    view: 'active', // active | trash
    onlyFav: false,
    sortBy: 'name', // name | first | last | updated
    prefsOpen: false,
    avatarUrls: {}, // avatarRef -> objectURL (decrypted, cached)
    _avatarPending: {},

    async init() {
        this._loadPrefs();
        await this._initZk();
        this.reconcileBlobs();
        this._checkAnniversaries();
        // Deep link from a linked gallery person (?c=<id>) → open that contact.
        const cid = new URLSearchParams(location.search).get('c');
        if (cid && this.contacts.some((c) => c.id === cid)) this.open(this.contacts.find((c) => c.id === cid));
    },

    // Zero-knowledge birthday / anniversary alerts: the client (which holds the
    // decrypted data) detects a due date and relays a one-off message through the
    // user's chosen channels. Deduped once per year per contact via a flag in the
    // sealed manifest; a 7-day look-back catches days the app wasn't opened.
    _checkAnniversaries() {
        if (this.state !== 'ready') return;
        const bch = config.birthdayChannels || [], ach = config.anniversaryChannels || [];
        if (! bch.length && ! ach.length) return;
        const now = new Date();
        const year = now.getFullYear();
        const startOfToday = new Date(year, now.getMonth(), now.getDate());
        const due = (iso) => {
            if (! iso || iso.length < 10) return false;
            const [, m, d] = iso.split('-').map(Number);
            if (! m || ! d) return false;
            const diff = (startOfToday - new Date(year, m - 1, d)) / 86400000;
            return diff >= 0 && diff <= 7;
        };
        let changed = false;
        for (const c of this.contacts) {
            if (c.trashed) continue;
            if (bch.length && c.bday && c.bdayNotified !== year && due(c.bday)) { this._fireAlert('birthday', c); c.bdayNotified = year; changed = true; }
            if (ach.length && c.anniversary && c.annivNotified !== year && due(c.anniversary)) { this._fireAlert('anniversary', c); c.annivNotified = year; changed = true; }
        }
        if (changed) this._save();
    },
    _fireAlert(kind, c) {
        const name = this.displayName(c);
        const title = kind === 'birthday' ? labels.bdayTitle : labels.annivTitle;
        const body = (kind === 'birthday' ? labels.bdayBody : labels.annivBody).replace(':name', name);
        postForm(config.notifyUrl, { kind, title, body }).catch(() => {});
    },

    // Display preferences (name order + sort) are per-device UI state, not
    // sensitive contact data — kept in localStorage, not the sealed manifest.
    _loadPrefs() {
        try {
            const p = JSON.parse(localStorage.getItem('ll-contacts-prefs') || '{}');
            if (p.sortBy) this.sortBy = p.sortBy;
        } catch (e) { /* defaults */ }
    },
    _savePrefs() {
        try { localStorage.setItem('ll-contacts-prefs', JSON.stringify({ sortBy: this.sortBy })); } catch (e) { /* ignore */ }
    },
    setSortBy(v) { this.sortBy = v; this._savePrefs(); },

    get allCategories() {
        const set = new Set();
        for (const c of this.contacts) if (! c.trashed) for (const g of (c.categories ?? [])) set.add(g);
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this._trashCount(this.contacts); },
    get current() { return this.contacts.find((c) => c.id === this.currentId) ?? null; },

    // First/last, from the structured fields or (for imported fn-only cards)
    // derived from the formatted name so the order/sort toggles still work.
    _nameParts(c) { return contactNameParts(c); },
    // Display label — always "Last, First".
    displayName(c) {
        if (! c) return '';
        return contactDisplayName(c) || (labels.unnamed || '—');
    },
    initials(c) {
        const { first, last } = this._nameParts(c);
        const from = ((first[0] || '') + (last[0] || '')) || (c.org || '?')[0];
        return (from || '?').toUpperCase();
    },
    // Sort key for the current sort mode.
    _sortKey(c) {
        if (this.sortBy === 'updated') return c.updated || '';
        const { first, last } = this._nameParts(c);
        if (this.sortBy === 'first') return (first || last || c.fn || '').toLowerCase();
        if (this.sortBy === 'last') return (last || first || c.fn || '').toLowerCase();
        return this.displayName(c).toLowerCase();
    },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.contacts.filter((c) => this.view === 'trash' ? c.trashed : ! c.trashed);
        if (this.onlyFav && this.view !== 'trash') list = list.filter((c) => c.favorite);
        if (this.activeTag !== '') list = list.filter((c) => (c.categories ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((c) => this.displayName(c).toLowerCase().includes(q)
                || (c.org ?? '').toLowerCase().includes(q)
                || (c.emails ?? []).some((e) => (e.value ?? '').toLowerCase().includes(q))
                || (c.phones ?? []).some((p) => (p.value ?? '').toLowerCase().includes(q))
                || (c.categories ?? []).some((g) => g.toLowerCase().includes(q)));
        }
        const dir = this.sortBy === 'updated' ? -1 : 1; // most-recent first for updated
        return [...list].sort((a, b) => dir * this._sortKey(a).localeCompare(this._sortKey(b)));
    },

    // OpenStreetMap search link for an address (opened in a new tab on click).
    osmUrl(a) {
        const q = [a.street, [a.zip, a.city].filter(Boolean).join(' '), a.region, a.country].filter(Boolean).join(', ');
        return 'https://www.openstreetmap.org/search?query=' + encodeURIComponent(q);
    },
    _addrQuery(a) {
        return [a.street, a.zip, a.city, a.region, a.country].filter(Boolean).join(', ').trim();
    },
    _geoCache: {},
    // Geocode an address (server proxy) and drop a small pin map beside it in the
    // read-only view. Results are cached per address string; a miss hides the map.
    async contactMap(el, a) {
        const q = this._addrQuery(a);
        if (! el || ! q) { if (el) el.style.display = 'none'; return; }
        let pt = this._geoCache[q];
        if (pt === undefined) {
            try {
                const geoData = await getJson('/gallery/geocode?q=' + encodeURIComponent(q));
                const r = (geoData.results || [])[0];
                pt = r && r.lat != null ? { lat: r.lat, lng: r.lng } : null;
            } catch (e) { pt = null; }
            this._geoCache[q] = pt;
        }
        if (! pt) { el.style.display = 'none'; return; }
        el.style.display = '';
        if (el._map) { el._map.remove(); el._map = null; }
        const L = await loadLeaflet();
        const map = L.map(el, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, keyboard: false, touchZoom: false, zoomAnimation: false }).setView([pt.lat, pt.lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
        L.marker([pt.lat, pt.lng]).addTo(map);
        el._map = map;
        setTimeout(() => { if (el._map) el._map.invalidateSize(); }, 120);
    },
    // Format an ISO date (yyyy-mm-dd) for display in the reader's locale.
    fmtDate(d) {
        if (! d) return '';
        const dt = new Date(d + (d.length === 10 ? 'T00:00:00' : ''));
        return isNaN(dt.getTime()) ? d : dt.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    },
    // Human address block (for the read-only view): street / zip city / region / country.
    addressLines(a) {
        if (! a) return [];
        return [
            (a.street || '').trim(),
            [a.zip, a.city].filter(Boolean).join(' ').trim(),
            (a.region || '').trim(),
            (a.country || '').trim(),
        ].filter(Boolean);
    },

    _newUid() { return 'urn:uuid:' + window.LLModuleStore.contacts.newId(); },
    newContact() {
        const c = {
            id: window.LLModuleStore.contacts.newId(), uid: this._newUid(),
            fn: '', first: '', last: '', middle: '', prefix: '', suffix: '', nickname: '',
            org: '', department: '', title: '', role: '', vatId: '',
            emails: [], phones: [], impp: [], addresses: [], urls: [],
            bday: '', anniversary: '', note: '', categories: [], favorite: false,
            avatarRef: null, avatarKey: null, personId: null, _x: [],
            trashed: false, updated: new Date().toISOString(),
        };
        this.contacts.unshift(c);
        this._save();
        this.open(c);
        this.editing = true; // a fresh contact opens straight in edit mode
    },
    open(c) {
        // Backfill fields added in later versions so legacy contacts render/edit.
        c.emails ??= []; c.phones ??= []; c.impp ??= []; c.addresses ??= []; c.urls ??= []; c.categories ??= []; c._x ??= []; c.vatId ??= '';
        // Normalise any raw multi-token vCard types (e.g. "cell,voice,pref") so
        // the labels/selects match a single known value.
        for (const e of c.emails) e.type = VCard.normType(e.type, 'home');
        for (const p of c.phones) p.type = VCard.normType(p.type, 'cell');
        for (const m of c.impp) m.type = VCard.normType(m.type, 'home');
        for (const a of c.addresses) { a.type = VCard.normType(a.type, 'home'); Object.assign(a, VCard.normAddr(a)); }
        // The dedicated full-name field was removed; split a legacy fn-only contact
        // into the first/last parts so those editor fields are populated + editable.
        if (! (c.first || '').trim() && ! (c.last || '').trim() && (c.fn || '').trim()) {
            const { first, last } = this._nameParts(c);
            c.first = first; c.last = last;
        }
        this.currentId = c.id; this.editing = false; this.tagsValue = (c.categories ?? []).join(', ');
    },
    // Localised label for a normalised contact-field type.
    typeLabel(t) {
        return { home: labels.typeHome, work: labels.typeWork, cell: labels.typeCell, other: labels.typeOther }[t] || t || '';
    },
    close() { this.currentId = null; this.editing = false; },
    startEdit() { this.tagsValue = (this.current?.categories ?? []).join(', '); this.editing = true; },

    // Persist the current contact (categories parsed from the tag input).
    save() {
        const c = this.current;
        if (! c) return;
        c.categories = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        // Compose the full name (vCard FN) from the parts — the standalone name
        // field is gone, so the parts are the source of truth.
        const parts = [c.prefix, c.first, c.middle, c.last, c.suffix].filter(Boolean).join(' ').trim();
        if (parts) c.fn = parts;
        else if (! (c.fn || '').trim()) c.fn = (c.org || '').trim();
        c.updated = new Date().toISOString();
        this._save();
    },

    // Repeatable-field rows.
    addEmail() { (this.current.emails ??= []).push({ value: '', type: 'home' }); this._save(); },
    addPhone() { (this.current.phones ??= []).push({ value: '', type: 'cell' }); this._save(); },
    addUrl() { (this.current.urls ??= []).push({ value: '', type: 'home' }); this._save(); },
    addImpp() { (this.current.impp ??= []).push({ value: '', type: 'home' }); this._save(); },
    addAddress() { (this.current.addresses ??= []).push({ street: '', city: '', region: '', zip: '', country: '', type: 'home' }); this._save(); },
    removeRow(list, i) { list.splice(i, 1); this._save(); },

    toggleFavorite(c) { c.favorite = ! c.favorite; c.updated = new Date().toISOString(); this._save(); },
    trash(c) { c.trashed = new Date().toISOString(); if (this.currentId === c.id) this.currentId = null; this._save(); },
    restore(c) { c.trashed = false; this._save(); },
    async remove(c) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        if (c.avatarRef) this._freeAvatar(c.avatarRef);
        const i = this.contacts.findIndex((x) => x.id === c.id);
        if (i >= 0) this.contacts.splice(i, 1);
        if (this.currentId === c.id) this.currentId = null;
        this._save();
    },
    async emptyTrash() {
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm)) return;
        for (const c of this.contacts.filter((x) => x.trashed)) if (c.avatarRef) this._freeAvatar(c.avatarRef);
        this.contacts = this.contacts.filter((x) => ! x.trashed);
        this._save();
    },

    /* ---- Avatar (encrypted blob, kept out of the manifest) ---- */
    avatarMenu: false,
    // Encrypt cropped avatar bytes → upload → set on the current contact.
    async _setAvatarFromBytes(bytes) {
        const c = this.current;
        if (! bytes || ! c) return;
        try {
            const enc = window.Vault.encryptContent(bytes, { name: 'avatar.jpg', mime: 'image/jpeg' });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const ref = await this._uploadContactBlob(cipher);
            const old = c.avatarRef;
            c.avatarRef = ref; c.avatarKey = enc.encFileKey; c.updated = new Date().toISOString();
            // Seed the display cache from the plaintext crop so the avatar updates
            // immediately (no decrypt round-trip, no reload). Reassign the whole map
            // so the new key is reliably reactive in the list + header at once.
            this.avatarUrls = { ...this.avatarUrls, [ref]: URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' })) };
            this._save();
            if (old) this._freeAvatar(old);
        } catch (e) { window.llToast?.(labels.avatarFailed || 'Upload failed'); }
    },
    // Source 1: upload a file → crop → set.
    async pickAvatar(ev) {
        const file = ev.target?.files?.[0];
        ev.target.value = '';
        this.avatarMenu = false;
        if (! file || ! this.current) return;
        const bytes = await window.llCrop(file);
        if (bytes) await this._setAvatarFromBytes(bytes);
    },
    removeAvatar(c) {
        if (! c?.avatarRef) return;
        const old = c.avatarRef;
        c.avatarRef = null; c.avatarKey = null; c.updated = new Date().toISOString();
        this._save();
        this._freeAvatar(old);
    },

    /* ---- Avatar source 2: pick from Files (images in the /store manifest) ---- */
    filePicker: false,
    _fileThumbs: {},
    fileImages() {
        return (window.LLFilesStore?.data?.files || []).filter((f) => ! f.trashed && (f.mime || '').startsWith('image/') && f.blob);
    },
    openFilePicker() { this.avatarMenu = false; this.filePicker = true; },
    closeFilePicker() { this.filePicker = false; },
    async fileThumb(f) {
        if (this._fileThumbs[f.blob]) return this._fileThumbs[f.blob];
        try { const b = await fetchDecrypt('/files/raw', f.blob, f.encFileKey); const u = URL.createObjectURL(new Blob([b], { type: f.mime || 'image/jpeg' })); this._fileThumbs[f.blob] = u; return u; } catch (e) { return ''; }
    },
    async pickFromFile(f) {
        this.filePicker = false;
        try {
            const bytes = await fetchDecrypt('/files/raw', f.blob, f.encFileKey);
            const out = await window.llCrop(new Blob([bytes], { type: f.mime || 'image/jpeg' }));
            if (out) await this._setAvatarFromBytes(out);
        } catch (e) { window.llToast?.(labels.avatarFailed || 'Failed'); }
    },

    /* ---- Avatar source 3: pick from Gallery (lazy-boot the gallery manifest) ---- */
    galleryPicker: false,
    galleryLoading: false,
    gTab: 'all',            // 'all' | 'people' | 'albums'
    gSel: null,             // drilled person/album id
    _galleryPhotos: [],
    _galleryPeople: [],
    _galleryAlbums: [],
    _galleryById: {},
    _galleryThumbs: {},
    async openGalleryPicker() {
        this.avatarMenu = false;
        this.galleryLoading = true;
        try {
            if (! await bootGalleryStore(this.$store)) { window.llToast?.(labels.avatarFailed || 'Vault locked'); return; }
            const d = window.LLGalleryStore.data || {};
            this._galleryPhotos = (d.photos || []).filter((p) => ! p.trashed && p.media_type !== 'video' && p.thumbRef);
            this._galleryById = Object.fromEntries(this._galleryPhotos.map((p) => [p.id, p]));
            this._galleryPeople = (d.people || []).filter((pp) => ! pp.hidden && (pp.faces || []).length);
            this._galleryAlbums = (d.albums || []).filter((a) => (a.photoIds || []).length);
            this.gTab = 'all'; this.gSel = null;
            this.galleryPicker = true;
        } finally { this.galleryLoading = false; }
    },
    closeGalleryPicker() { this.galleryPicker = false; this.gSel = null; },
    gSetTab(t) { this.gTab = t; this.gSel = null; },
    gShowChooser() { return (this.gTab === 'people' || this.gTab === 'albums') && ! this.gSel; },
    gGridPhotos() {
        if (this.gTab === 'all') return this._galleryPhotos;
        if (this.gTab === 'people') {
            const pp = this._galleryPeople.find((x) => x.id === this.gSel);
            if (! pp) return [];
            const ids = [...new Set((pp.faces || []).map((f) => f.photoId))];
            return ids.map((id) => this._galleryById[id]).filter(Boolean);
        }
        const al = this._galleryAlbums.find((x) => x.id === this.gSel);
        return al ? (al.photoIds || []).map((id) => this._galleryById[id]).filter(Boolean) : [];
    },
    gInitials(name) { return (name || '?').trim().split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?'; },
    async gPersonCover(pp) {
        const f = (pp.faces || [])[0];
        if (! f?.cropRef) return '';
        if (this._galleryThumbs[f.cropRef]) return this._galleryThumbs[f.cropRef];
        try { const b = await fetchDecrypt('/gallery/raw', f.cropRef, f.cropKey); const u = URL.createObjectURL(new Blob([b], { type: 'image/jpeg' })); this._galleryThumbs[f.cropRef] = u; return u; } catch (e) { return ''; }
    },
    async gAlbumCover(al) {
        const p = this._galleryById[al.cover] || this._galleryById[(al.photoIds || [])[0]];
        return p ? this.galleryThumb(p) : '';
    },
    async galleryThumb(p) {
        if (this._galleryThumbs[p.thumbRef]) return this._galleryThumbs[p.thumbRef];
        try { const b = await fetchDecrypt('/gallery/raw', p.thumbRef, p.thumbKey); const u = URL.createObjectURL(new Blob([b], { type: 'image/jpeg' })); this._galleryThumbs[p.thumbRef] = u; return u; } catch (e) { return ''; }
    },
    async pickFromGallery(p) {
        this.galleryPicker = false;
        try {
            const ref = p.mediumRef || p.originalRef || p.thumbRef;
            const key = p.mediumKey || p.originalKey || p.thumbKey;
            const bytes = await fetchDecrypt('/gallery/raw', ref, key);
            const out = await window.llCrop(new Blob([bytes], { type: 'image/jpeg' }));
            if (out) await this._setAvatarFromBytes(out);
        } catch (e) { window.llToast?.(labels.avatarFailed || 'Failed'); }
    },
    async avatarFor(c) {
        if (! c?.avatarRef) return '';
        if (this.avatarUrls[c.avatarRef]) return this.avatarUrls[c.avatarRef];
        if (this._avatarPending[c.avatarRef]) return this._avatarPending[c.avatarRef];
        const job = (async () => {
            const bytes = await fetchDecrypt(config.rawBase, c.avatarRef, c.avatarKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.avatarUrls[c.avatarRef] = url;
            return url;
        })().catch(() => '').finally(() => { delete this._avatarPending[c.avatarRef]; });
        this._avatarPending[c.avatarRef] = job;
        return job;
    },
    _revokeAvatars() { for (const k in this.avatarUrls) URL.revokeObjectURL(this.avatarUrls[k]); this.avatarUrls = {}; },

    // Decode + center-crop + downscale to a square JPEG (keeps avatars tiny).
    async _squareJpeg(file, size) {
        const img = await createImageBitmap(file);
        const s = Math.min(img.width, img.height);
        const sx = (img.width - s) / 2, sy = (img.height - s) / 2;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = size;
        canvas.getContext('2d').drawImage(img, sx, sy, s, s, 0, 0, size, size);
        const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.82));
        return new Uint8Array(await blob.arrayBuffer());
    },

    _uploadContactBlob(file) {
        const data = new FormData();
        data.append('_token', config.token);
        data.append('file', file, file.name);
        return fetch(config.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data })
            .then((r) => { if (! r.ok) throw new Error('upload'); return r.json(); })
            .then((j) => j.id);
    },
    _freeAvatar(ref) {
        if (this.avatarUrls[ref]) { URL.revokeObjectURL(this.avatarUrls[ref]); delete this.avatarUrls[ref]; }
        queueBlobDelete(config.blobBase.replace('__id__', ref), config.token);
    },
    // Tell the server which avatar blobs are still referenced; it frees the rest.
    reconcileBlobs() {
        if (this.state !== 'ready') return;
        if (Date.now() - _contactsReconAt < 60000) return; // dedupe across mounts
        _contactsReconAt = Date.now();
        const blobs = [];
        for (const c of this.contacts) if (c.avatarRef) blobs.push(c.avatarRef);
        postForm(config.reconcileUrl, { blobs: [...new Set(blobs)] }).catch(() => {});
    },

    /* ---- vCard import / export (client-side, ZK) ---- */
    importing: false,
    // Decrypt an avatar blob into a base64 data URI for the PHOTO property.
    async _avatarDataUri(c) {
        if (! c?.avatarRef) return null;
        try {
            const bytes = await fetchDecrypt(config.rawBase, c.avatarRef, c.avatarKey);
            let bin = '';
            for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
            return 'data:image/jpeg;base64,' + btoa(bin);
        } catch (e) { return null; }
    },
    _download(name, text) {
        const url = URL.createObjectURL(new Blob([text], { type: 'text/vcard;charset=utf-8' }));
        const a = document.createElement('a');
        a.href = url; a.download = name; a.click();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    },
    async exportOne(c) {
        const vcf = VCard.build(c, await this._avatarDataUri(c));
        this._download((this.displayName(c).replace(/[^\w.-]+/g, '_') || 'contact') + '.vcf', vcf);
    },
    async exportAll() {
        const active = this.contacts.filter((c) => ! c.trashed);
        let out = '';
        for (const c of active) out += VCard.build(c, await this._avatarDataUri(c));
        this._download('contacts.vcf', out);
    },
    async importFile(ev) {
        const file = ev.target?.files?.[0];
        ev.target.value = '';
        if (! file) return;
        this.importing = true;
        try {
            const text = await file.text();
            const cards = VCard.parse(text);
            const known = new Set(this.contacts.map((c) => c.uid).filter(Boolean));
            let added = 0;
            for (const { c: parsed, photo } of cards) {
                if (parsed.uid && known.has(parsed.uid)) continue; // dedupe by UID
                const c = {
                    id: window.LLModuleStore.contacts.newId(), uid: parsed.uid || this._newUid(),
                    fn: parsed.fn, first: parsed.first, last: parsed.last, middle: parsed.middle, prefix: parsed.prefix, suffix: parsed.suffix, nickname: parsed.nickname,
                    org: parsed.org, department: parsed.department, title: parsed.title, role: parsed.role,
                    emails: parsed.emails, phones: parsed.phones, impp: parsed.impp, addresses: parsed.addresses, urls: parsed.urls,
                    bday: parsed.bday, anniversary: parsed.anniversary, note: parsed.note, categories: parsed.categories, favorite: false,
                    avatarRef: null, avatarKey: null, personId: null, _x: parsed._x,
                    trashed: false, updated: new Date().toISOString(),
                };
                if (photo) {
                    try {
                        const b64 = photo.slice(photo.indexOf(',') + 1);
                        const bin = atob(b64);
                        const bytes = new Uint8Array(bin.length);
                        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
                        const sq = await this._squareJpeg(new Blob([bytes]), 256);
                        const enc = window.Vault.encryptContent(sq, { name: 'avatar.jpg', mime: 'image/jpeg' });
                        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
                        c.avatarRef = await this._uploadContactBlob(cipher); c.avatarKey = enc.encFileKey;
                    } catch (e) { /* skip a bad photo, keep the contact */ }
                }
                this.contacts.unshift(c);
                if (parsed.uid) known.add(parsed.uid);
                added++;
            }
            this._save();
            window.llToast?.((labels.imported || ':n imported').replace(':n', added));
        } catch (e) { window.llToast?.(labels.importFailed || 'Import failed'); } finally { this.importing = false; }
    },

    /* ---- Link to a Gallery person (cross-manifest: /store + /gallery/store) ---- */
    personPicker: false,
    personLoading: false,
    personQuery: '',
    _people: [],
    _personCovers: {},
    get linkedPersonName() { return this.current?.personName || ''; },
    galleryHref(c) { return c?.personId ? ('/gallery?person=' + encodeURIComponent(c.personId)) : '#'; },
    async openPersonPicker() {
        if (! this.current) return;
        this.personLoading = true;
        this.personQuery = '';
        try {
            if (! await bootGalleryStore(this.$store)) return;
            this._people = (window.LLGalleryStore.data.people || []).filter((p) => ! p.hidden && (p.faces || []).length);
            this.personPicker = true;
        } finally { this.personLoading = false; }
    },
    closePersonPicker() { this.personPicker = false; },
    personSuggestions() {
        const q = this.personQuery.trim().toLowerCase();
        const name = this.displayName(this.current).toLowerCase();
        let list = this._people;
        if (q) list = list.filter((p) => (p.name || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => {
            const am = name && (a.name || '').toLowerCase().includes(name) ? 0 : 1;
            const bm = name && (b.name || '').toLowerCase().includes(name) ? 0 : 1;
            return (am - bm) || (a.name || '').localeCompare(b.name || '');
        });
    },
    personInitials(pp) { const n = (pp.name || '').trim(); return n ? n.split(/\s+/).slice(0, 2).map((s) => s[0].toUpperCase()).join('') : '?'; },
    linkPerson(pp) {
        const c = this.current;
        if (! c || ! pp) { this.personPicker = false; return; }
        c.personId = pp.id; c.personName = pp.name || ''; c.updated = new Date().toISOString();
        // Write the gallery-person snapshot so the gallery shows the link too.
        pp.contactId = c.id;
        // Store the natural "First Last" order so the gallery snapshot matches
        // what the People picker shows (displayName is "Last, First").
        const np = this._nameParts(c);
        pp.contactName = (np.first || np.last) ? [np.first, np.last].filter(Boolean).join(' ') : (c.fn || c.org || '').trim();
        pp.contactFirst = np.first; pp.contactLast = np.last; // snapshot for "Last, First" gallery display
        pp.contactAvatarRef = c.avatarRef || null;
        pp.contactAvatarKey = c.avatarKey || null;
        window.LLGalleryStore.touch();
        this._save();
        this.personPicker = false;
    },
    async unlinkPerson() {
        const c = this.current;
        if (! c?.personId) return;
        const pid = c.personId;
        c.personId = null; c.personName = null;
        this._save();
        try {
            if (await bootGalleryStore(this.$store)) {
                const pp = (window.LLGalleryStore.data?.people || []).find((x) => x.id === pid);
                if (pp && pp.contactId === c.id) { pp.contactId = null; pp.contactName = null; pp.contactFirst = null; pp.contactLast = null; pp.contactAvatarRef = null; pp.contactAvatarKey = null; window.LLGalleryStore.touch(); }
            }
        } catch (e) { /* best effort */ }
    },
    async personCoverUrl(pp) {
        const cover = (pp.faces || [])[0];
        if (! cover?.cropRef) return '';
        if (this._personCovers[cover.cropRef]) return this._personCovers[cover.cropRef];
        try {
            const bytes = await fetchDecrypt('/gallery/raw', cover.cropRef, cover.cropKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this._personCovers[cover.cropRef] = url;
            return url;
        } catch (e) { return ''; }
    },
});
