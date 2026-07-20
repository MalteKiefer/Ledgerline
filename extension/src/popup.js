import jsQR from 'jsqr';
import { generate, GEN_LANGS } from './generator.js';
import { esc } from './esc.js';
import { hostOf, matchScore } from './hosts.js';
import { IDENTITY_LABELS } from './identity.js';

const app = document.getElementById('app');
const links = document.getElementById('links');
const send = (msg) => new Promise((r) => chrome.runtime.sendMessage(msg, r));

function el(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }

// Monochrome icons (heroicons outline), rendered as inline SVG so the popup
// needs no external assets and inherits currentColor.
const PATHS = {
    key: '<path d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>',
    refresh: '<path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.99v4.99"/>',
    lock: '<path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>',
    back: '<path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>',
    clipboard: '<path d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612 0 .414-.336.75-.75.75H9a.75.75 0 0 1-.75-.75c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>',
    eye: '<path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
    eyeslash: '<path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>',
    open: '<path d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>',
    magnifier: '<path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>',
    login: '<path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>',
    plus: '<path d="M12 4.5v15m7.5-7.5h-15"/>',
    trash: '<path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>',
    qr: '<path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/><path d="M6.75 6.75h.008v.008H6.75V6.75ZM6.75 16.5h.008v.008H6.75V16.5ZM16.5 6.75h.008v.008H16.5V6.75ZM13.5 13.5h.008v.008H13.5V13.5ZM13.5 19.5h.008v.008H13.5V19.5ZM19.5 13.5h.008v.008H19.5V13.5ZM19.5 19.5h.008v.008H19.5V19.5ZM16.5 16.5h.008v.008H16.5V16.5Z"/>',
    star: '<path d="M11.48 3.5a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>',
    pencil: '<path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>',
    bookmark: '<path d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>',
    clock: '<path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
    folder: '<path d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v8.25A2.25 2.25 0 0 0 4.5 16.5h15a2.25 2.25 0 0 0 2.25-2.25V9.75a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>',
    'folder-plus': '<path d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>',
};
function icon(name, size = 18) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="${size}" height="${size}">${PATHS[name] || ''}</svg>`;
}
function iconBtn(id, name, title) { return `<button class="ic" id="${id}" title="${esc(title)}" aria-label="${esc(title)}">${icon(name)}</button>`; }

async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}

const TYPE = { login: 'Login', password: 'Password', card: 'Card', wifi: 'Wi-Fi', license: 'License', server: 'Server', passkey: 'Passkey', identity: 'Identity', secure_note: 'Secure note' };
let totpTimer = null;
function stopTotp() { if (totpTimer) { clearInterval(totpTimer); totpTimer = null; } }

// Active main view: 'passwords' | 'bookmarks'
let mainView = 'passwords';

async function render() {
    stopTotp();
    const st = await send({ type: 'getState' });
    links.innerHTML = '';
    if (! st.paired) return renderPair();
    if (! st.unlocked) return renderUnlock();
    return renderMain();
}

function renderPair() {
    app.innerHTML = '';
    app.append(el(`<div>
        <p class="hint">Open your Ledgerline profile, start a command-line/extension pairing and copy the code. Approve the device there after connecting.</p>
        <label class="fld">Server URL</label><input id="url" placeholder="https://home.example.com" value="https://">
        <label class="fld">Pairing code</label><input id="code" placeholder="paste code">
        <button class="primary" id="go">Connect</button>
        <p class="err" id="err"></p>
    </div>`));
    document.getElementById('go').onclick = async () => {
        const serverUrl = document.getElementById('url').value.trim();
        const code = document.getElementById('code').value.trim();
        if (! serverUrl || ! code) return;
        document.getElementById('go').textContent = 'Waiting for approval…';
        document.getElementById('go').disabled = true;
        const r = await send({ type: 'pair', serverUrl, code });
        if (r.ok) render(); else { document.getElementById('err').textContent = 'Pairing failed or timed out.'; document.getElementById('go').textContent = 'Connect'; document.getElementById('go').disabled = false; }
    };
}

function renderUnlock() {
    links.innerHTML = iconBtn('unpair', 'back', 'Unpair');
    document.getElementById('unpair').onclick = async () => { await send({ type: 'unpair' }); render(); };
    app.innerHTML = '';
    app.append(el(`<div>
        <p class="hint">Enter your vault passphrase to unlock. It stays in this browser session only — never sent to the server.</p>
        <label class="fld">Vault passphrase</label><input id="pass" type="password">
        <button class="primary" id="go">Unlock</button>
        <p class="err" id="err"></p>
    </div>`));
    const go = document.getElementById('go');
    const submit = async () => {
        go.disabled = true; go.textContent = 'Unlocking…';
        const r = await send({ type: 'unlock', passphrase: document.getElementById('pass').value });
        if (r.ok) render(); else { document.getElementById('err').textContent = 'Wrong passphrase.'; go.disabled = false; go.textContent = 'Unlock'; }
    };
    go.onclick = submit;
    document.getElementById('pass').addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
    document.getElementById('pass').focus();
}

// list filters
let filterFolder = '';
let filterTag = '';
let selected = null;
let showAll = false; // when the current site matches entries, list is prefiltered to them
let tfaMap = null; // { domain: documentationUrl } from 2fa.directory

// The matched dataset domain for a login's URLs (walking parent domains), or ''.
function tfaMatch(it) {
    if (! tfaMap || ! it || it.type !== 'login') return '';
    for (const u of (it.urls || [])) {
        let d = hostOf(/^https?:\/\//.test(u) ? u : 'https://' + u);
        while (d && d.includes('.')) { if (d in tfaMap) return d; d = d.slice(d.indexOf('.') + 1); }
    }
    return '';
}
// A login with no stored TOTP whose site is known to support app 2FA.
function supports2fa(it) { return ! (it && it.hasTotp) && ! ! tfaMatch(it); }

async function renderMain() {
    if (mainView === 'bookmarks') { return renderBookmarks(); }
    filterFolder = ''; filterTag = ''; selected = null; showAll = false;
    links.innerHTML = iconBtn('new', 'plus', 'New login') + iconBtn('gen', 'key', 'Generate password') + iconBtn('refresh', 'refresh', 'Refresh from server') + iconBtn('lock', 'lock', 'Lock');
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    document.getElementById('gen').onclick = () => renderGen();
    app.innerHTML = '';
    app.append(el(`<div class="seg" style="margin-bottom:10px">
        <button id="sw-pw" class="on">Passwords</button>
        <button id="sw-bm">Bookmarks</button>
    </div>`));
    document.getElementById('sw-pw').classList.toggle('on', true);
    document.getElementById('sw-pw').onclick = () => { mainView = 'passwords'; renderMain(); };
    document.getElementById('sw-bm').onclick = () => { mainView = 'bookmarks'; renderMain(); };
    app.append(el(`<div class="cols">
        <div class="list">
            <div class="top">
                <div class="search">${icon('magnifier', 16)}<input id="q" placeholder="Search…" autofocus></div>
                <select id="folder"></select>
                <div class="chips" id="tags"></div>
            </div>
            <ul id="list"></ul>
        </div>
        <div class="detail" id="detail"></div>
    </div>`));

    const tab = await activeTab();
    const host = tab ? hostOf(tab.url) : '';
    document.getElementById('new').onclick = () => renderNew({ url: host ? 'https://' + host : '' });
    const q = document.getElementById('q');
    const listEl = document.getElementById('list');
    const folderEl = document.getElementById('folder');
    const tagsEl = document.getElementById('tags');
    const detailEl = document.getElementById('detail');

    let library = [];
    let folders = [];

    function paintNav() {
        const counts = {};
        for (const it of library) counts[it.folder || '_none'] = (counts[it.folder || '_none'] || 0) + 1;
        folderEl.innerHTML = `<option value="">All items (${library.length})</option>`
            + folders.map((f) => `<option value="${esc(f.id)}"${filterFolder === f.id ? ' selected' : ''}>${esc(f.name)} (${counts[f.id] || 0})</option>`).join('')
            + (counts._none ? `<option value="_none"${filterFolder === '_none' ? ' selected' : ''}>No folder (${counts._none})</option>` : '');
        const tags = [...new Set(library.flatMap((it) => it.tags || []))].sort((a, b) => a.localeCompare(b));
        tagsEl.innerHTML = '';
        for (const t of tags) {
            const c = el(`<button class="chip${filterTag === t ? ' on' : ''}">#${esc(t)}</button>`);
            c.onclick = () => { filterTag = filterTag === t ? '' : t; paintNav(); paint(q.value); };
            tagsEl.append(c);
        }
    }

    function avatar(it, big) {
        if (it.type === 'login' && it.icon) return `<span class="av"><img src="${esc(it.icon)}" alt=""></span>`;
        const letter = esc((it.title || it.username || '?')[0].toUpperCase());
        return `<span class="av mono">${letter}</span>`;
    }

    async function paint(query) {
        const r = await send({ type: 'search', query });
        let items = r.logins || [];
        if (filterFolder === '_none') items = items.filter((x) => ! x.folder);
        else if (filterFolder) items = items.filter((x) => x.folder === filterFolder);
        if (filterTag) items = items.filter((x) => (x.tags || []).includes(filterTag));
        // On a recognized login site, prefilter to just the matching entries
        // until the user searches, picks a folder/tag, or asks to show all.
        const siteMatches = items.filter((x) => matchScore(x, host));
        const prefilter = ! query && ! filterFolder && ! filterTag && ! showAll && siteMatches.length > 0;
        if (prefilter) items = siteMatches;
        items = items.slice().sort((a, b) => matchScore(b, host) - matchScore(a, host));
        listEl.innerHTML = '';
        if (! items.length) { listEl.append(el('<li class="muted">Nothing found</li>')); return; }
        for (const it of items) {
            const sub = it.username || (it.urls || [])[0] || TYPE[it.type] || it.type;
            const li = el(`<li><button class="row${selected && selected.id === it.id ? ' on' : ''}">
                ${avatar(it)}
                <span class="grow"><div class="t">${esc(it.title)}</div><div class="u">${esc(sub)}</div></span>
                ${it.favorite ? `<span class="star">${icon('star', 14)}</span>` : ''}
            </button></li>`);
            li.querySelector('button').onclick = () => { selected = it; paint(q.value); showDetail(it, tab); };
            listEl.append(li);
        }
        if (prefilter && library.length > items.length) {
            const more = el(`<li><button class="row" style="justify-content:center;color:#9ca3af">Show all items (${library.length})</button></li>`);
            more.querySelector('button').onclick = () => { showAll = true; paint(q.value); };
            listEl.append(more);
        }
    }

    function showDetail(it, tab) {
        stopTotp();
        renderDetailView(it, tab);
    }

    function renderDetailView(it, tab) {
        stopTotp();
        const rows = [];
        const secrets = {};
        let si = 0;
        const field = (label, valHtml, actions) => `<div class="field"><div class="flabel">${esc(label)}</div><div class="frow">${valHtml}${actions || ''}</div></div>`;
        const copyBtn = (v) => `<button class="ic" data-copy="${esc(v)}" title="Copy">${icon('clipboard', 16)}</button>`;
        const plain = (label, v) => field(label, `<span class="fval">${esc(v)}</span>`, copyBtn(v));
        const secret = (label, v) => {
            const id = 's' + (si++); secrets[id] = v;
            return `<div class="field"><div class="flabel">${esc(label)}</div><div class="frow"><span class="fval mono" data-sec="${id}">••••••••••</span><button class="ic" data-reveal="${id}" title="Reveal">${icon('eye', 16)}</button>${copyBtn(v)}</div></div>`;
        };

        if (it.type === 'card') {
            if (it.cardholder) rows.push(plain('Cardholder', it.cardholder));
            if (it.number) rows.push(secret('Card number', it.number));
            if (it.expiry) rows.push(plain('Expiry', it.expiry));
            if (it.cvv) rows.push(secret('CVV', it.cvv));
        } else if (it.type === 'identity') {
            for (const [k, label] of IDENTITY_LABELS) {
                if (it[k]) rows.push(plain(label, it[k]));
            }
        } else {
            if (it.username) rows.push(plain('Username', it.username));
            if (it.password) rows.push(secret('Password', it.password));
        }
        if (it.hasTotp) rows.push(`<div class="field totp"><div class="flabel">One-time code</div><div class="frow"><span class="fval code" id="totpCode">······</span><span class="remain" id="totpRemain"></span><button class="ic" id="totpCopy" title="Copy">${icon('clipboard', 16)}</button></div></div>`);
        for (const u of (it.urls || [])) rows.push(field('Website', `<a class="fval" href="${esc(/^https?:\/\//.test(u) ? u : 'https://' + u)}" target="_blank" rel="noopener noreferrer">${esc(u)}</a>`, `<button class="ic" data-open="${esc(u)}" title="Open">${icon('open', 16)}</button>` + copyBtn(u)));
        if (it.note) rows.push(field('Note', `<span class="fval" style="white-space:pre-wrap">${esc(it.note)}</span>`, ''));

        // Embedded passkeys: list each with metadata only (no private/public key).
        // Shared items are read-only — never show remove on shared items.
        const embeddedPasskeys = (it.type === 'login' && ! it.shared && Array.isArray(it.passkeys) && it.passkeys.length > 0)
            ? it.passkeys.map((pk) => `<div class="field"><div class="flabel">Passkey</div><div class="frow"><span class="fval">${esc(pk.userName || pk.rpId || 'Passkey')}</span><button class="ic" data-rm-pk="${esc(pk.credentialId)}" title="Remove passkey">${icon('trash', 16)}</button></div></div>`).join('')
            : '';

        const tags = (it.tags || []).length ? `<div class="dtags">${it.tags.map((t) => `<span class="tg">#${esc(t)}</span>`).join('')}</div>` : '';
        const canFill = it.type === 'login' || (it.type === 'card' && it.number) || ! ! it.password;
        const fillLabel = it.type === 'card' ? 'Fill card on this page' : 'Fill on this page';
        const rawDoc = tfaMap ? (tfaMap[tfaMatch(it)] || '') : '';
        const doc = /^https?:\/\//i.test(rawDoc) ? rawDoc : '';
        const tfaHtml = supports2fa(it)
            ? `<div class="tfahint">${icon('key', 16)}<div><div>This website offers two-factor authentication. Add a one-time code to this login.</div>${doc ? `<a href="${esc(doc)}" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;color:inherit">How to enable it</a>` : ''}</div></div>`
            : '';
        const canScan = it.type === 'login' && ! it.hasTotp && ! it.shared;
        const canEdit = (it.type === 'login' || it.type === 'password') && ! it.shared;
        const canTrash = ! it.shared;
        detailEl.innerHTML = '';
        detailEl.append(el(`<div>
            <div class="dhead">${avatar(it, true)}<div class="grow"><div class="dtitle">${esc(it.title)}</div><div class="dtype">${esc(TYPE[it.type] || it.type)}${it.shared ? ' <span class="muted" style="font-size:11px">(shared)</span>' : ''}</div></div>
              <div class="dactions">
                ${canEdit ? `<button class="ic" id="edit" title="Edit">${icon('pencil', 18)}</button>` : ''}
                ${canScan ? `<button class="ic" id="scan2fa" title="Scan a 2FA QR code">${icon('qr', 18)}</button>` : ''}
                ${canTrash ? `<button class="ic" id="del" title="Move to trash">${icon('trash', 18)}</button>` : ''}
              </div>
            </div>
            ${tfaHtml}
            ${rows.join('')}
            ${embeddedPasskeys}
            ${tags}
            ${canFill ? `<button class="fillbtn" id="fillBtn">${icon('login', 16)} ${esc(fillLabel)}</button>` : ''}
        </div>`));

        detailEl.querySelectorAll('[data-copy]').forEach((b) => b.onclick = () => navigator.clipboard.writeText(b.dataset.copy).catch(() => {}));
        detailEl.querySelectorAll('[data-open]').forEach((b) => b.onclick = () => { const u = b.dataset.open; chrome.tabs.create({ url: /^https?:\/\//.test(u) ? u : 'https://' + u }); });
        detailEl.querySelectorAll('[data-reveal]').forEach((b) => {
            const id = b.dataset.reveal; let shown = false;
            b.onclick = () => { shown = ! shown; detailEl.querySelector(`[data-sec="${id}"]`).textContent = shown ? secrets[id] : '••••••••••'; b.innerHTML = icon(shown ? 'eyeslash' : 'eye', 16); };
        });
        // Per-passkey remove buttons. Uses credentialId (non-secret) to identify
        // the entry; the background filters the stored full array so private keys
        // of remaining passkeys are never lost.
        detailEl.querySelectorAll('[data-rm-pk]').forEach((b) => {
            const credentialId = b.dataset.rmPk;
            b.onclick = async () => {
                const pkEntry = (it.passkeys || []).find((p) => p.credentialId === credentialId);
                const label = pkEntry ? (pkEntry.userName || pkEntry.rpId || 'this passkey') : 'this passkey';
                if (! confirm(`Remove ${label} from this login?`)) return;
                b.disabled = true;
                try {
                    const r = await send({ type: 'removePasskey', id: it.id, credentialId });
                    if (r?.ok) {
                        await reloadLibrary();
                        const fresh = library.find((x) => x.id === it.id);
                        if (fresh) { selected = fresh; renderDetailView(fresh, tab); } else renderDetailView(it, tab);
                    } else {
                        b.disabled = false;
                        alert(r?.error === 'locked' ? 'Unlock the vault first.' : 'Could not remove passkey.');
                    }
                } catch (e) {
                    b.disabled = false;
                    alert('Could not remove passkey.');
                }
            };
        });
        const fillBtn = detailEl.querySelector('#fillBtn');
        if (fillBtn) fillBtn.onclick = () => it.type === 'card' ? fillCardOnPage(it, tab) : fill(it, tab);
        const delBtn = detailEl.querySelector('#del');
        if (delBtn) delBtn.onclick = async () => {
            if (! confirm('Move this entry to the trash?')) return;
            delBtn.disabled = true;
            try {
                const r = await send({ type: 'trashItem', id: it.id });
                if (r?.ok) { selected = null; await reloadLibrary(); detailEl.innerHTML = '<div class="empty">Select an entry to view its details.</div>'; }
                else { delBtn.disabled = false; alert(r?.error === 'locked' ? 'Unlock the vault first.' : 'Could not delete.'); }
            } catch (e) {
                delBtn.disabled = false;
                alert('Could not delete.');
            }
        };
        const editBtn = detailEl.querySelector('#edit');
        if (editBtn) editBtn.onclick = () => renderEditView(it, tab);
        const scanBtn = detailEl.querySelector('#scan2fa');
        if (scanBtn) scanBtn.onclick = () => scan2fa(it);

        if (it.hasTotp) {
            const tick = async () => {
                const r = await send({ type: 'totp', id: it.id });
                const codeEl = document.getElementById('totpCode'); const remEl = document.getElementById('totpRemain');
                if (! codeEl) { stopTotp(); return; }
                if (r?.ok && r.code) { codeEl.textContent = r.code.replace(/(\d{3})(\d{3})/, '$1 $2'); remEl.textContent = (r.remain ?? '') + 's'; }
                const copyEl = document.getElementById('totpCopy');
                if (copyEl && r?.code) copyEl.onclick = () => navigator.clipboard.writeText(r.code).catch(() => {});
            };
            tick(); totpTimer = setInterval(tick, 1000);
        }
    }

    function renderEditView(it, tab) {
        stopTotp();
        const isLogin = it.type === 'login';
        detailEl.innerHTML = '';
        // Build edit form fields depending on item type.
        // login: username, password, first url, totp secret, note
        // password: password, note
        const urlVal = esc((it.urls || [])[0] || '');
        const totpHint = it.hasTotp ? ' (leave blank to keep existing)' : '';
        const loginFields = isLogin ? `
            <label class="fld">Username</label><input id="e-user" value="${esc(it.username || '')}">
            <label class="fld">Password</label><input id="e-pass" type="password" value="${esc(it.password || '')}">
            <label class="fld">Website URL</label><input id="e-url" value="${urlVal}">
            <label class="fld">TOTP secret${esc(totpHint)}</label><input id="e-totp" autocomplete="off">
            <label class="fld">Note</label><textarea id="e-note" rows="3" style="resize:vertical">${esc(it.note || '')}</textarea>
        ` : `
            <label class="fld">Password</label><input id="e-pass" type="password" value="${esc(it.password || '')}">
            <label class="fld">Note</label><textarea id="e-note" rows="3" style="resize:vertical">${esc(it.note || '')}</textarea>
        `;
        detailEl.append(el(`<div>
            <div class="dhead">${avatar(it, true)}<div class="grow"><div class="dtitle">${esc(it.title)}</div><div class="dtype">${esc(TYPE[it.type] || it.type)}</div></div></div>
            ${loginFields}
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="primary" id="e-save" style="flex:1">Save</button>
                <button id="e-cancel" style="flex:1">Cancel</button>
            </div>
            <p class="err" id="e-err"></p>
        </div>`));
        const $ = (id) => document.getElementById(id);
        $('e-cancel').onclick = () => renderDetailView(it, tab);
        $('e-save').onclick = async () => {
            $('e-save').disabled = true; $('e-save').textContent = 'Saving…';
            const patch = {};
            if (isLogin) {
                patch.username = $('e-user').value;
                patch.password = $('e-pass').value;
                // Replace first URL only when the field has a value; blank = keep
                // existing URLs (mirrors TOTP blank=keep semantics — never silently wipe).
                const newUrl = $('e-url').value.trim();
                const oldUrls = it.urls || [];
                patch.urls = newUrl ? [newUrl, ...oldUrls.slice(1)] : oldUrls;
                const totpVal = $('e-totp').value.trim();
                if (totpVal) patch.totp = totpVal; // only overwrite TOTP if a new value was entered
                patch.note = $('e-note').value;
            } else {
                patch.password = $('e-pass').value;
                patch.note = $('e-note').value;
            }
            try {
                const r = await send({ type: 'updateItem', id: it.id, patch });
                if (r?.ok) {
                    await reloadLibrary();
                    const fresh = library.find((x) => x.id === it.id);
                    selected = fresh || it;
                    renderDetailView(fresh || it, tab);
                } else {
                    $('e-save').disabled = false; $('e-save').textContent = 'Save';
                    $('e-err').textContent = r?.error === 'locked' ? 'Unlock the vault first.' : 'Could not save.';
                }
            } catch (e) {
                $('e-save').disabled = false; $('e-save').textContent = 'Save';
                $('e-err').textContent = 'Could not save.';
            }
        };
        const passEl = $('e-pass');
        if (passEl) passEl.focus();
    }

    async function reloadLibrary() {
        library = (await send({ type: 'search', query: '' })).logins || [];
        folders = (await send({ type: 'folders' })).folders || [];
        paintNav();
        paint(q.value);
    }
    // Scan a TOTP QR shown on the current tab and attach it to this login.
    async function scan2fa(it) {
        try {
            const dataUrl = await chrome.tabs.captureVisibleTab(tab.windowId, { format: 'png' });
            const img = new Image(); img.src = dataUrl; await img.decode();
            const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight;
            const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
            const d = ctx.getImageData(0, 0, c.width, c.height);
            const code = jsQR(d.data, d.width, d.height);
            if (! code || ! /^otpauth:\/\//i.test(code.data)) { alert('No 2FA QR code found on the current tab. Make sure the QR is visible, then try again.'); return; }
            const r = await send({ type: 'updateItem', id: it.id, patch: { totp: code.data } });
            if (! r?.ok) { alert(r?.error === 'locked' ? 'Unlock the vault first.' : 'Could not save the code.'); return; }
            await reloadLibrary();
            const fresh = library.find((x) => x.id === it.id);
            if (fresh) { selected = fresh; showDetail(fresh, tab); }
        } catch (e) { alert('Could not capture the tab to scan a QR code.'); }
    }

    const first = await send({ type: 'search', query: '' });
    library = first.logins || [];
    folders = (await send({ type: 'folders' })).folders || [];
    tfaMap = (await send({ type: 'tfa' })).entries || {};
    paintNav();
    q.addEventListener('input', () => paint(q.value));
    folderEl.onchange = () => { filterFolder = folderEl.value; paint(q.value); };
    document.getElementById('refresh').onclick = async () => {
        const rf = document.getElementById('refresh');
        rf.disabled = true;
        await send({ type: 'refresh' });
        library = (await send({ type: 'search', query: '' })).logins || [];
        folders = (await send({ type: 'folders' })).folders || [];
        rf.disabled = false;
        paintNav(); paint(q.value);
    };
    // Preselect the best match for the current site (matching 1Password).
    const matches = library.filter((x) => matchScore(x, host)).sort((a, b) => (a.title || '').localeCompare(b.title || ''));
    if (matches.length) { selected = matches[0]; showDetail(selected, tab); }
    else detailEl.innerHTML = '<div class="empty">Select an entry to view its details.</div>';
    paint('');
}

// --- Bookmarks view ---
async function renderBookmarks() {
    stopTotp();
    links.innerHTML = iconBtn('lock', 'lock', 'Lock');
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    app.innerHTML = '';

    // View switcher
    app.append(el(`<div class="seg" style="margin-bottom:10px">
        <button id="sw-pw">Passwords</button>
        <button id="sw-bm" class="on">Bookmarks</button>
    </div>`));
    document.getElementById('sw-pw').onclick = () => { mainView = 'passwords'; renderMain(); };
    document.getElementById('sw-bm').onclick = () => { mainView = 'bookmarks'; renderBookmarks(); };

    // Toolbar: Save page + Folder+
    app.append(el(`<div style="display:flex;gap:6px;margin-bottom:8px">
        <button class="primary" id="bm-save" style="flex:1;margin-top:0;padding:7px 10px;display:flex;align-items:center;justify-content:center;gap:5px">${icon('bookmark', 14)} Save page</button>
        <button class="ic" id="bm-folder-new" title="New folder" style="width:auto;padding:0 10px;border:1px solid #d1d5db;border-radius:8px">${icon('folder-plus', 16)}</button>
    </div>`));

    // Search + filter row
    app.append(el(`<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">
        <div class="search" style="position:relative">${icon('magnifier', 16)}<input id="bm-q" placeholder="Search bookmarks…" style="padding-left:30px"></div>
        <div class="chips" id="bm-chips"></div>
    </div>`));

    // Folder dropdown
    app.append(el(`<div style="margin-bottom:8px"><select id="bm-folder-filter"></select></div>`));

    // Bookmark list
    app.append(el(`<ul id="bm-list" style="list-style:none;margin:0;padding:0;overflow-y:auto;max-height:310px"></ul>`));

    // Detail/edit panel (below list, shown inline)
    app.append(el(`<div id="bm-detail"></div>`));

    // Load data
    let bmData = await send({ type: 'bookmarks.list' });
    let bookmarks = bmData.bookmarks || [];
    let folders = bmData.folders || [];

    // Active filter state (bookmarks view-local)
    let bmFilter = 'all'; // 'all' | 'favorites' | 'readlater'
    let bmFolderFilter = '';
    let bmQuery = '';
    let bmSelected = null;

    function folderDepth(id, visited = new Set()) {
        if (!id || visited.has(id)) return 0;
        visited.add(id);
        const f = folders.find((x) => x.id === id);
        if (!f || !f.parentId) return 0;
        return 1 + folderDepth(f.parentId, visited);
    }

    function paintFolderFilter() {
        const sel = document.getElementById('bm-folder-filter');
        if (!sel) return;
        sel.innerHTML = `<option value="">All folders</option>`
            + folders.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((f) => {
                const depth = folderDepth(f.id);
                const indent = '    '.repeat(depth);
                return `<option value="${esc(f.id)}"${bmFolderFilter === f.id ? ' selected' : ''}>${indent}${esc(f.name)}</option>`;
            }).join('');
        sel.onchange = () => { bmFolderFilter = sel.value; paintList(); };
    }

    function paintChips() {
        const chips = document.getElementById('bm-chips');
        if (!chips) return;
        const defs = [
            { id: 'all', label: 'All' },
            { id: 'favorites', label: `${icon('star', 12)} Favorites` },
            { id: 'readlater', label: `${icon('clock', 12)} Read later` },
        ];
        chips.innerHTML = '';
        for (const d of defs) {
            const c = el(`<button class="chip${bmFilter === d.id ? ' on' : ''}" style="display:inline-flex;align-items:center;gap:3px">${d.label}</button>`);
            c.onclick = () => { bmFilter = d.id; paintChips(); paintList(); };
            chips.append(c);
        }
    }

    function filteredBookmarks() {
        let items = bookmarks;
        if (bmQuery) {
            const q = bmQuery.toLowerCase();
            items = items.filter((b) => (b.title || '').toLowerCase().includes(q) || (b.url || '').toLowerCase().includes(q) || (b.tags || []).some((t) => t.toLowerCase().includes(q)));
        }
        if (bmFilter === 'favorites') items = items.filter((b) => b.favorite);
        else if (bmFilter === 'readlater') items = items.filter((b) => b.readLater);
        if (bmFolderFilter) items = items.filter((b) => b.folderId === bmFolderFilter);
        return items;
    }

    function paintList() {
        const listEl = document.getElementById('bm-list');
        if (!listEl) return;
        const items = filteredBookmarks();
        listEl.innerHTML = '';
        if (!items.length) { listEl.append(el('<li class="muted">No bookmarks found</li>')); return; }
        for (const bm of items) {
            const folderName = bm.folderId ? (folders.find((f) => f.id === bm.folderId) || {}).name || '' : '';
            const li = el(`<li><button class="row${bmSelected && bmSelected.id === bm.id ? ' on' : ''}">
                <span class="av mono" style="font-size:11px">${icon('bookmark', 14)}</span>
                <span class="grow">
                    <div class="t">${esc(bm.title || bm.url || 'Untitled')}</div>
                    <div class="u">${esc(bm.url || '')}</div>
                </span>
                ${bm.favorite ? `<span class="star">${icon('star', 13)}</span>` : ''}
                ${bm.readLater ? `<span style="color:#6b7280">${icon('clock', 13)}</span>` : ''}
                ${folderName ? `<span style="color:#9ca3af;font-size:10px">${icon('folder', 12)}</span>` : ''}
            </button></li>`);
            li.querySelector('button').onclick = () => { bmSelected = bm; paintList(); showBmDetail(bm); };
            listEl.append(li);
        }
    }

    function showBmDetail(bm) {
        const detailEl = document.getElementById('bm-detail');
        if (!detailEl) return;
        renderBmDetail(bm, detailEl);
    }

    function renderBmDetail(bm, detailEl) {
        const tagsVal = (bm.tags || []).join(', ');
        const isSafeLink = /^https?:\/\//i.test(bm.url);
        detailEl.innerHTML = '';
        detailEl.append(el(`<div style="margin-top:10px;border-top:1px solid #0000001a;padding-top:10px">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span class="grow" style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(bm.title || 'Untitled')}</span>
                <button class="ic" id="bm-edit" title="Edit">${icon('pencil', 16)}</button>
                <button class="ic" id="bm-del" title="Move to trash">${icon('trash', 16)}</button>
            </div>
            ${bm.url ? `<div style="margin-bottom:6px;display:flex;align-items:center;gap:4px">
                ${isSafeLink
                    ? `<a class="fval" style="flex:1;color:inherit;text-decoration:underline;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px" href="${esc(bm.url)}" target="_blank" rel="noopener noreferrer">${esc(bm.url)}</a><button class="ic" id="bm-open" title="Open in new tab" style="flex:none">${icon('open', 15)}</button>`
                    : `<span style="flex:1;font-size:12px;color:#9ca3af;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(bm.url)}</span><span style="font-size:11px;color:#dc2626;flex:none">unsafe URL</span>`
                }
            </div>` : ''}
            ${bm.description ? `<div style="font-size:12px;color:#6b7280;margin-bottom:6px;white-space:pre-wrap">${esc(bm.description)}</div>` : ''}
            ${(bm.tags || []).length ? `<div class="dtags" style="margin-bottom:6px">${bm.tags.map((t) => `<span class="tg">#${esc(t)}</span>`).join('')}</div>` : ''}
            ${tagsVal || bm.folderId ? `<div style="display:flex;gap:8px;font-size:11px;color:#9ca3af">
                ${bm.folderId ? `<span>${icon('folder', 11)} ${esc((folders.find((f) => f.id === bm.folderId) || {}).name || '')}</span>` : ''}
                ${bm.favorite ? `<span>${icon('star', 11)} Favorite</span>` : ''}
                ${bm.readLater ? `<span>${icon('clock', 11)} Read later</span>` : ''}
            </div>` : ''}
            <p class="err" id="bm-det-err"></p>
        </div>`));
        const openBtn = detailEl.querySelector('#bm-open');
        if (openBtn) openBtn.onclick = () => chrome.tabs.create({ url: bm.url });
        detailEl.querySelector('#bm-del').onclick = async () => {
            if (!confirm('Move this bookmark to trash?')) return;
            const btn = detailEl.querySelector('#bm-del');
            btn.disabled = true;
            const r = await send({ type: 'bookmarks.trash', id: bm.id });
            if (r?.ok) {
                bmSelected = null; detailEl.innerHTML = ''; bmData = await send({ type: 'bookmarks.list' }); bookmarks = bmData.bookmarks || []; folders = bmData.folders || []; paintFolderFilter(); paintList();
            } else {
                btn.disabled = false; const errEl = detailEl.querySelector('#bm-det-err'); if (errEl) errEl.textContent = 'Could not delete.';
            }
        };
        detailEl.querySelector('#bm-edit').onclick = () => renderBmEdit(bm, detailEl);
    }

    function renderBmEdit(bm, detailEl) {
        const folderOpts = `<option value="">No folder</option>` + folders.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((f) => {
            const depth = folderDepth(f.id);
            const indent = '    '.repeat(depth);
            return `<option value="${esc(f.id)}"${bm.folderId === f.id ? ' selected' : ''}>${indent}${esc(f.name)}</option>`;
        }).join('');
        detailEl.innerHTML = '';
        detailEl.append(el(`<div style="margin-top:10px;border-top:1px solid #0000001a;padding-top:10px">
            <label class="fld">Title</label><input id="be-title" value="${esc(bm.title || '')}">
            <label class="fld">URL</label><input id="be-url" value="${esc(bm.url || '')}">
            <label class="fld">Description</label><textarea id="be-desc" rows="2" style="resize:vertical">${esc(bm.description || '')}</textarea>
            <label class="fld">Tags (comma-separated)</label><input id="be-tags" value="${esc((bm.tags || []).join(', '))}">
            <label class="fld">Folder</label><select id="be-folder">${folderOpts}</select>
            <div class="opts" style="margin-top:8px">
                <label><input type="checkbox" id="be-fav"${bm.favorite ? ' checked' : ''}> Favorite</label>
                <label><input type="checkbox" id="be-rl"${bm.readLater ? ' checked' : ''}> Read later</label>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="primary" id="be-save" style="flex:1;margin-top:0">Save</button>
                <button id="be-cancel" style="flex:1;padding:9px;border:1px solid #d1d5db;border-radius:8px;background:transparent;color:inherit;cursor:pointer">Cancel</button>
            </div>
            <p class="err" id="be-err"></p>
        </div>`));
        const $ = (id) => document.getElementById(id);
        $('be-cancel').onclick = () => renderBmDetail(bm, detailEl);
        $('be-save').onclick = async () => {
            $('be-save').disabled = true; $('be-save').textContent = 'Saving…';
            const rawTags = $('be-tags').value.split(',').map((t) => t.trim()).filter(Boolean);
            const patch = {
                title: $('be-title').value.trim(),
                url: $('be-url').value.trim(),
                description: $('be-desc').value,
                tags: rawTags,
                folderId: $('be-folder').value || null,
                favorite: $('be-fav').checked,
                readLater: $('be-rl').checked,
            };
            const r = await send({ type: 'bookmarks.update', id: bm.id, patch });
            if (r?.ok) {
                bmData = await send({ type: 'bookmarks.list' }); bookmarks = bmData.bookmarks || []; folders = bmData.folders || [];
                const fresh = bookmarks.find((x) => x.id === bm.id);
                bmSelected = fresh || null;
                paintFolderFilter(); paintList();
                if (fresh) renderBmDetail(fresh, detailEl); else detailEl.innerHTML = '';
            } else {
                $('be-save').disabled = false; $('be-save').textContent = 'Save';
                const errEl = $('be-err'); if (errEl) errEl.textContent = 'Could not save.';
            }
        };
    }

    // Save current page
    async function renderSavePage() {
        const tab = await activeTab();
        const prefillTitle = tab ? (tab.title || '') : '';
        const prefillUrl = tab ? (tab.url || '') : '';
        const detailEl = document.getElementById('bm-detail');
        if (!detailEl) return;
        const folderOpts = `<option value="">No folder</option>` + folders.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((f) => {
            const depth = folderDepth(f.id);
            const indent = '    '.repeat(depth);
            return `<option value="${esc(f.id)}">${indent}${esc(f.name)}</option>`;
        }).join('');
        detailEl.innerHTML = '';
        detailEl.append(el(`<div style="margin-top:10px;border-top:1px solid #0000001a;padding-top:10px">
            <div style="font-weight:600;margin-bottom:8px">${icon('bookmark', 14)} Save current page</div>
            <label class="fld">Title</label><input id="sp-title" value="${esc(prefillTitle)}">
            <label class="fld">URL</label><input id="sp-url" value="${esc(prefillUrl)}">
            <label class="fld">Description</label><textarea id="sp-desc" rows="2" style="resize:vertical"></textarea>
            <label class="fld">Tags (comma-separated)</label><input id="sp-tags">
            <label class="fld">Folder</label><select id="sp-folder">${folderOpts}</select>
            <div class="opts" style="margin-top:8px">
                <label><input type="checkbox" id="sp-fav"> Favorite</label>
                <label><input type="checkbox" id="sp-rl"> Read later</label>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="primary" id="sp-save" style="flex:1;margin-top:0">Save bookmark</button>
                <button id="sp-cancel" style="flex:1;padding:9px;border:1px solid #d1d5db;border-radius:8px;background:transparent;color:inherit;cursor:pointer">Cancel</button>
            </div>
            <p class="err" id="sp-err"></p>
        </div>`));
        const $ = (id) => document.getElementById(id);
        $('sp-title').focus();
        $('sp-cancel').onclick = () => { detailEl.innerHTML = ''; bmSelected = null; };
        $('sp-save').onclick = async () => {
            $('sp-save').disabled = true; $('sp-save').textContent = 'Saving…';
            const rawTags = $('sp-tags').value.split(',').map((t) => t.trim()).filter(Boolean);
            const bookmark = {
                title: $('sp-title').value.trim(),
                url: $('sp-url').value.trim(),
                description: $('sp-desc').value,
                tags: rawTags,
                folderId: $('sp-folder').value || null,
                favorite: $('sp-fav').checked,
                readLater: $('sp-rl').checked,
            };
            const r = await send({ type: 'bookmarks.create', bookmark });
            if (r?.id) {
                bmData = await send({ type: 'bookmarks.list' }); bookmarks = bmData.bookmarks || []; folders = bmData.folders || [];
                paintFolderFilter(); paintList();
                const fresh = bookmarks.find((x) => x.id === r.id);
                if (fresh) { bmSelected = fresh; renderBmDetail(fresh, detailEl); } else detailEl.innerHTML = '';
            } else {
                $('sp-save').disabled = false; $('sp-save').textContent = 'Save bookmark';
                const errEl = $('sp-err'); if (errEl) errEl.textContent = 'Could not save.';
            }
        };
    }

    // Folder management
    function renderFolderCreate() {
        const detailEl = document.getElementById('bm-detail');
        if (!detailEl) return;
        const folderOpts = `<option value="">Root (no parent)</option>` + folders.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((f) => {
            const depth = folderDepth(f.id);
            const indent = '    '.repeat(depth);
            return `<option value="${esc(f.id)}">${indent}${esc(f.name)}</option>`;
        }).join('');
        detailEl.innerHTML = '';
        detailEl.append(el(`<div style="margin-top:10px;border-top:1px solid #0000001a;padding-top:10px">
            <div style="font-weight:600;margin-bottom:8px">${icon('folder-plus', 14)} New folder</div>
            <label class="fld">Folder name</label><input id="fc-name">
            <label class="fld">Parent folder</label><select id="fc-parent">${folderOpts}</select>
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="primary" id="fc-save" style="flex:1;margin-top:0">Create folder</button>
                <button id="fc-cancel" style="flex:1;padding:9px;border:1px solid #d1d5db;border-radius:8px;background:transparent;color:inherit;cursor:pointer">Cancel</button>
            </div>
            <p class="err" id="fc-err"></p>
            ${folders.length ? `<div style="margin-top:12px">
                <div style="font-size:11px;color:#6b7280;margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Existing folders</div>
                <ul id="fc-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px"></ul>
            </div>` : ''}
        </div>`));
        const $ = (id) => document.getElementById(id);
        $('fc-name').focus();
        $('fc-cancel').onclick = () => { detailEl.innerHTML = ''; };
        $('fc-save').onclick = async () => {
            const name = $('fc-name').value.trim();
            if (!name) { const e = $('fc-err'); if (e) e.textContent = 'Name required.'; return; }
            $('fc-save').disabled = true; $('fc-save').textContent = 'Creating…';
            const parentId = $('fc-parent').value || null;
            const r = await send({ type: 'bookmarkFolders.create', name, parentId });
            if (r?.id) {
                bmData = await send({ type: 'bookmarks.list' }); bookmarks = bmData.bookmarks || []; folders = bmData.folders || [];
                paintFolderFilter(); paintList(); renderFolderCreate();
            } else {
                $('fc-save').disabled = false; $('fc-save').textContent = 'Create folder';
                const e = $('fc-err'); if (e) e.textContent = 'Could not create folder.';
            }
        };
        // Existing folders list with rename/delete
        const fcList = document.getElementById('fc-list');
        if (fcList) {
            for (const f of folders.slice().sort((a, b) => (a.name || '').localeCompare(b.name || ''))) {
                const depth = folderDepth(f.id);
                const indent = '  '.repeat(depth);
                const li = el(`<li style="display:flex;align-items:center;gap:4px;padding:3px 0">
                    <span style="flex:1;font-size:12px">${indent}${icon('folder', 13)} <span class="fc-fname">${esc(f.name)}</span></span>
                    <button class="ic" data-rename="${esc(f.id)}" title="Rename">${icon('pencil', 14)}</button>
                    <button class="ic" data-delfolder="${esc(f.id)}" title="Delete folder">${icon('trash', 14)}</button>
                </li>`);
                fcList.append(li);
            }
            fcList.querySelectorAll('[data-rename]').forEach((btn) => {
                btn.onclick = () => {
                    const id = btn.dataset.rename;
                    const folder = folders.find((x) => x.id === id);
                    if (!folder) return;
                    const newName = prompt(`Rename folder "${folder.name}" to:`, folder.name);
                    if (!newName || !newName.trim()) return;
                    send({ type: 'bookmarkFolders.rename', id, name: newName.trim() }).then((r) => {
                        if (r?.ok) { send({ type: 'bookmarks.list' }).then((d) => { bmData = d; bookmarks = d.bookmarks || []; folders = d.folders || []; paintFolderFilter(); paintList(); renderFolderCreate(); }); }
                        else alert('Could not rename folder.');
                    });
                };
            });
            fcList.querySelectorAll('[data-delfolder]').forEach((btn) => {
                btn.onclick = () => {
                    const id = btn.dataset.delfolder;
                    const folder = folders.find((x) => x.id === id);
                    if (!folder) return;
                    if (!confirm(`Delete folder "${folder.name}"? Bookmarks inside will be moved to root.`)) return;
                    send({ type: 'bookmarkFolders.delete', id }).then((r) => {
                        if (r?.ok) { send({ type: 'bookmarks.list' }).then((d) => { bmData = d; bookmarks = d.bookmarks || []; folders = d.folders || []; paintFolderFilter(); paintList(); renderFolderCreate(); }); }
                        else alert('Could not delete folder.');
                    });
                };
            });
        }
    }

    // Wire up toolbar
    document.getElementById('bm-save').onclick = () => renderSavePage();
    document.getElementById('bm-folder-new').onclick = () => renderFolderCreate();

    // Wire up search + filter
    document.getElementById('bm-q').addEventListener('input', (e) => { bmQuery = e.target.value; paintList(); });

    // Initial paint
    paintChips();
    paintFolderFilter();
    paintList();
}

// --- Create a new login ---
function renderNew(prefill = {}) {
    stopTotp();
    links.innerHTML = iconBtn('back', 'back', 'Back');
    document.getElementById('back').onclick = () => render();
    app.innerHTML = '';
    app.append(el(`<div>
        <label class="fld">Title</label><input id="n-title">
        <label class="fld">Username</label><input id="n-user">
        <label class="fld">Password</label>
        <div class="prev" style="margin-top:4px"><input id="n-pass" type="text" class="grow" style="border:0;background:transparent;padding:0"><button class="ic" id="n-gen" title="Generate">${icon('refresh', 16)}</button><button class="ic" id="n-copy" title="Copy">${icon('clipboard', 16)}</button></div>
        <label class="fld">Website</label><input id="n-url">
        <button class="primary" id="n-save">Save login</button>
        <p class="err" id="n-err"></p>
    </div>`));
    const $ = (id) => document.getElementById(id);
    $('n-title').value = prefill.title || '';
    $('n-user').value = prefill.username || '';
    $('n-pass').value = prefill.password || '';
    $('n-url').value = prefill.url || '';
    $('n-gen').onclick = () => { $('n-pass').value = generate({ mode: 'chars', length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false }); };
    $('n-copy').onclick = () => navigator.clipboard.writeText($('n-pass').value).catch(() => {});
    $('n-save').onclick = async () => {
        const login = { title: $('n-title').value.trim(), username: $('n-user').value.trim(), password: $('n-pass').value, url: $('n-url').value.trim() };
        if (! login.title && ! login.username && ! login.url) { $('n-err').textContent = 'Enter at least a title or website.'; return; }
        $('n-save').disabled = true; $('n-save').textContent = 'Saving…';
        const r = await send({ type: 'createLogin', login });
        if (r?.ok) render();
        else { $('n-err').textContent = r?.error === 'locked' ? 'Unlock the vault first.' : 'Could not save.'; $('n-save').disabled = false; $('n-save').textContent = 'Save login'; }
    };
    $('n-title').focus();
}

// --- Password generator ---
const gen = { mode: 'chars', length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false, words: 4, lang: 'en', sep: '-', capitalize: true, number: true };

function renderGen() {
    stopTotp();
    links.innerHTML = iconBtn('back', 'back', 'Back');
    document.getElementById('back').onclick = () => render();
    app.innerHTML = '';
    const langOpts = GEN_LANGS.map((l) => `<option value="${l}">${l.toUpperCase()}</option>`).join('');
    app.append(el(`<div>
        <div class="prev"><span class="grow" id="prev"></span>
            <button class="ic" id="regen" title="Regenerate">${icon('refresh', 16)}</button>
            <button class="ic" id="copy" title="Copy">${icon('clipboard', 16)}</button>
        </div>
        <div class="seg"><button id="mChars" class="on">Characters</button><button id="mWords">Memorable words</button></div>
        <div id="cChars">
            <label class="rng">Length: <span id="lenv"></span><input type="range" min="8" max="64" id="len"></label>
            <div class="opts">
                <label><input type="checkbox" id="upper">A–Z</label>
                <label><input type="checkbox" id="lower">a–z</label>
                <label><input type="checkbox" id="digits">0–9</label>
                <label><input type="checkbox" id="symbols">!@#</label>
                <label><input type="checkbox" id="similar">Allow look-alike characters</label>
            </div>
        </div>
        <div id="cWords" style="display:none">
            <label class="rng">Words: <span id="wcv"></span><input type="range" min="3" max="8" id="wc"></label>
            <div class="grid2">
                <label>Language<select id="lang">${langOpts}</select></label>
                <label>Separator<select id="sep"><option value="-">-</option><option value=".">.</option><option value="_">_</option><option value="space">Space</option><option value="">None</option></select></label>
            </div>
            <div class="opts"><label><input type="checkbox" id="cap">Capitalize</label><label><input type="checkbox" id="num">Add number</label></div>
        </div>
        <button class="primary" id="use">Copy to clipboard</button>
    </div>`));

    const $ = (id) => document.getElementById(id);
    const prev = $('prev');
    const regen = () => { prev.textContent = generate(gen); };
    const syncMode = () => {
        $('mChars').classList.toggle('on', gen.mode === 'chars');
        $('mWords').classList.toggle('on', gen.mode === 'words');
        $('cChars').style.display = gen.mode === 'chars' ? '' : 'none';
        $('cWords').style.display = gen.mode === 'words' ? '' : 'none';
    };
    $('len').value = gen.length; $('lenv').textContent = gen.length;
    $('wc').value = gen.words; $('wcv').textContent = gen.words;
    $('lang').value = gen.lang; $('sep').value = gen.sep;
    $('mChars').onclick = () => { gen.mode = 'chars'; syncMode(); regen(); };
    $('mWords').onclick = () => { gen.mode = 'words'; syncMode(); regen(); };
    $('len').oninput = (e) => { gen.length = +e.target.value; $('lenv').textContent = gen.length; regen(); };
    $('wc').oninput = (e) => { gen.words = +e.target.value; $('wcv').textContent = gen.words; regen(); };
    for (const [id, key] of [['upper', 'upper'], ['lower', 'lower'], ['digits', 'digits'], ['symbols', 'symbols'], ['similar', 'similar'], ['cap', 'capitalize'], ['num', 'number']]) {
        $(id).checked = gen[key];
        $(id).onchange = (e) => { gen[key] = e.target.checked; regen(); };
    }
    $('lang').onchange = (e) => { gen.lang = e.target.value; regen(); };
    $('sep').onchange = (e) => { gen.sep = e.target.value; regen(); };
    $('regen').onclick = regen;
    const copyPrev = () => navigator.clipboard.writeText(prev.textContent).catch(() => {});
    $('copy').onclick = copyPrev;
    $('use').onclick = () => { copyPrev(); window.close(); };
    syncMode(); regen();
}

async function fillCardOnPage(card, tab) {
    if (! tab) return;
    try {
        await chrome.tabs.sendMessage(tab.id, { type: 'fillCard', card: { number: card.number, cardholder: card.cardholder, expiry: card.expiry, cvv: card.cvv } });
    } catch (e) { /* no content script / no card form here */ }
    window.close();
}

async function fill(login, tab) {
    if (! tab) return;
    let filled = false;
    try {
        const r = await chrome.tabs.sendMessage(tab.id, { type: 'fill', login: { id: login.id, username: login.username, password: login.password, hasTotp: login.hasTotp } });
        filled = ! ! (r && r.filled);
    } catch (e) { /* no content script on this page */ }
    if (! filled) {
        const u = (login.urls || [])[0];
        if (u) chrome.tabs.create({ url: /^https?:\/\//.test(u) ? u : 'https://' + u });
    }
    window.close();
}

render();
