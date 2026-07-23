import jsQR from 'jsqr';
import { generate, GEN_LANGS } from './generator.js';
import { esc } from './esc.js';
import { hostOf, matchScore } from './hosts.js';
import { IDENTITY_LABELS } from './identity.js';
import { t } from './i18n.js';

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
    'chevron': '<path d="M8.25 4.5l7.5 7.5-7.5 7.5"/>',
};
function icon(name, size = 18) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="${size}" height="${size}">${PATHS[name] || ''}</svg>`;
}
function iconBtn(id, name, title) { return `<button class="ic" id="${id}" title="${esc(title)}" aria-label="${esc(title)}">${icon(name)}</button>`; }

async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}

const TYPE = { login: t('type.login'), password: t('type.password'), card: t('type.card'), wifi: t('type.wifi'), license: t('type.license'), server: t('type.server'), passkey: t('type.passkey'), identity: t('type.identity'), secure_note: t('type.secure_note') };
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
        <p class="hint">${t('pair.hint')}</p>
        <label class="fld">${t('pair.server_url')}</label><input id="url" placeholder="${esc(t('pair.server_ph'))}" value="https://">
        <label class="fld">${t('pair.code_label')}</label><input id="code" placeholder="${esc(t('pair.code_ph'))}">
        <button class="primary" id="go">${t('pair.connect')}</button>
        <p class="err" id="err"></p>
    </div>`));
    document.getElementById('go').onclick = async () => {
        const serverUrl = document.getElementById('url').value.trim();
        const code = document.getElementById('code').value.trim();
        if (! serverUrl || ! code) return;
        document.getElementById('go').textContent = t('pair.waiting');
        document.getElementById('go').disabled = true;
        const r = await send({ type: 'pair', serverUrl, code });
        if (r.ok) render(); else { document.getElementById('err').textContent = t('pair.error'); document.getElementById('go').textContent = t('pair.connect'); document.getElementById('go').disabled = false; }
    };
}

function renderUnlock() {
    links.innerHTML = iconBtn('unpair', 'back', t('unlock.unpair'));
    document.getElementById('unpair').onclick = async () => { await send({ type: 'unpair' }); render(); };
    app.innerHTML = '';
    app.append(el(`<div>
        <p class="hint">${t('unlock.hint')}</p>
        <label class="fld">${t('unlock.pass_label')}</label><input id="pass" type="password">
        <button class="primary" id="go">${t('unlock.action')}</button>
        <p class="err" id="err"></p>
    </div>`));
    const go = document.getElementById('go');
    const submit = async () => {
        go.disabled = true; go.textContent = t('unlock.loading');
        const r = await send({ type: 'unlock', passphrase: document.getElementById('pass').value });
        if (r.ok) render(); else { document.getElementById('err').textContent = t('unlock.wrong'); go.disabled = false; go.textContent = t('unlock.action'); }
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
    links.innerHTML = iconBtn('new', 'plus', t('list.new_login')) + iconBtn('gen', 'key', t('list.generate')) + iconBtn('refresh', 'refresh', t('list.refresh')) + iconBtn('lock', 'lock', t('list.lock'));
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    document.getElementById('gen').onclick = () => renderGen();
    app.innerHTML = '';
    app.append(el(`<div class="seg" style="margin-bottom:10px">
        <button id="sw-pw" class="on">${t('nav.passwords')}</button>
        <button id="sw-bm">${t('nav.bookmarks')}</button>
    </div>`));
    document.getElementById('sw-pw').onclick = () => { mainView = 'passwords'; renderMain(); };
    document.getElementById('sw-bm').onclick = () => { mainView = 'bookmarks'; renderMain(); };
    app.append(el(`<div class="cols">
        <div class="list">
            <div class="top">
                <div class="search">${icon('magnifier', 16)}<input id="q" placeholder="${esc(t('list.search_ph'))}" autofocus></div>
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
        folderEl.innerHTML = `<option value="">${t('list.all_items', { count: library.length })}</option>`
            + folders.map((f) => `<option value="${esc(f.id)}"${filterFolder === f.id ? ' selected' : ''}>${esc(f.name)} (${counts[f.id] || 0})</option>`).join('')
            + (counts._none ? `<option value="_none"${filterFolder === '_none' ? ' selected' : ''}>${t('list.no_folder', { count: counts._none })}</option>` : '');
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
        if (! items.length) { listEl.append(el(`<li class="muted">${t('list.nothing_found')}</li>`)); return; }
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
            const more = el(`<li><button class="row" style="justify-content:center;color:#9ca3af">${t('list.show_all', { count: library.length })}</button></li>`);
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
        const copyBtn = (v) => `<button class="ic" data-copy="${esc(v)}" title="${esc(t('detail.copy'))}">${icon('clipboard', 16)}</button>`;
        const plain = (label, v) => field(label, `<span class="fval">${esc(v)}</span>`, copyBtn(v));
        const secret = (label, v) => {
            const id = 's' + (si++); secrets[id] = v;
            return `<div class="field"><div class="flabel">${esc(label)}</div><div class="frow"><span class="fval mono" data-sec="${id}">••••••••••</span><button class="ic" data-reveal="${id}" title="${esc(t('detail.reveal'))}">${icon('eye', 16)}</button>${copyBtn(v)}</div></div>`;
        };

        if (it.type === 'card') {
            if (it.cardholder) rows.push(plain(t('field.cardholder'), it.cardholder));
            if (it.number) rows.push(secret(t('field.card_number'), it.number));
            if (it.expiry) rows.push(plain(t('field.expiry'), it.expiry));
            if (it.cvv) rows.push(secret(t('field.cvv'), it.cvv));
        } else if (it.type === 'identity') {
            for (const [k, label] of IDENTITY_LABELS) {
                if (it[k]) rows.push(plain(label, it[k]));
            }
        } else {
            if (it.username) rows.push(plain(t('field.username'), it.username));
            if (it.password) rows.push(secret(t('field.password'), it.password));
        }
        if (it.hasTotp) rows.push(`<div class="field totp"><div class="flabel">${t('field.totp')}</div><div class="frow"><span class="fval code" id="totpCode">······</span><span class="remain" id="totpRemain"></span><button class="ic" id="totpCopy" title="${esc(t('detail.copy'))}">${icon('clipboard', 16)}</button></div></div>`);
        for (const u of (it.urls || [])) rows.push(field(t('field.website'), `<a class="fval" href="${esc(/^https?:\/\//.test(u) ? u : 'https://' + u)}" target="_blank" rel="noopener noreferrer">${esc(u)}</a>`, `<button class="ic" data-open="${esc(u)}" title="${esc(t('detail.open'))}">${icon('open', 16)}</button>` + copyBtn(u)));
        if (it.note) rows.push(field(t('field.note'), `<span class="fval" style="white-space:pre-wrap">${esc(it.note)}</span>`, ''));

        // Embedded passkeys: list each with metadata only (no private/public key).
        // Shared items are read-only — never show remove on shared items.
        const embeddedPasskeys = (it.type === 'login' && ! it.shared && Array.isArray(it.passkeys) && it.passkeys.length > 0)
            ? it.passkeys.map((pk) => `<div class="field"><div class="flabel">${t('field.passkey')}</div><div class="frow"><span class="fval">${esc(pk.userName || pk.rpId || t('field.passkey'))}</span><button class="ic" data-rm-pk="${esc(pk.credentialId)}" title="${esc(t('detail.remove_passkey_title'))}">${icon('trash', 16)}</button></div></div>`).join('')
            : '';

        const tags = (it.tags || []).length ? `<div class="dtags">${it.tags.map((t) => `<span class="tg">#${esc(t)}</span>`).join('')}</div>` : '';
        const canFill = it.type === 'login' || (it.type === 'card' && it.number) || ! ! it.password;
        const fillLabel = it.type === 'card' ? t('detail.fill_card') : t('detail.fill');
        const rawDoc = tfaMap ? (tfaMap[tfaMatch(it)] || '') : '';
        const doc = /^https?:\/\//i.test(rawDoc) ? rawDoc : '';
        const tfaHtml = supports2fa(it)
            ? `<div class="tfahint">${icon('key', 16)}<div><div>${t('detail.tfa_hint')}</div>${doc ? `<a href="${esc(doc)}" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;color:inherit">${t('detail.tfa_how')}</a>` : ''}</div></div>`
            : '';
        const canScan = it.type === 'login' && ! it.hasTotp && ! it.shared;
        const canEdit = (it.type === 'login' || it.type === 'password') && ! it.shared;
        const canTrash = ! it.shared;
        detailEl.innerHTML = '';
        detailEl.append(el(`<div>
            <div class="dhead">${avatar(it, true)}<div class="grow"><div class="dtitle">${esc(it.title)}</div><div class="dtype">${esc(TYPE[it.type] || it.type)}${it.shared ? ` <span class="muted" style="font-size:11px">${t('detail.shared_badge')}</span>` : ''}</div></div>
              <div class="dactions">
                ${canEdit ? `<button class="ic" id="edit" title="${esc(t('detail.edit'))}">${icon('pencil', 18)}</button>` : ''}
                ${canScan ? `<button class="ic" id="scan2fa" title="${esc(t('detail.scan2fa'))}">${icon('qr', 18)}</button>` : ''}
                ${canTrash ? `<button class="ic" id="del" title="${esc(t('detail.trash'))}">${icon('trash', 18)}</button>` : ''}
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
                const label = pkEntry ? (pkEntry.userName || pkEntry.rpId || t('field.passkey')) : t('field.passkey');
                if (! confirm(t('detail.remove_passkey_confirm', { label }))) return;
                b.disabled = true;
                try {
                    const r = await send({ type: 'removePasskey', id: it.id, credentialId });
                    if (r?.ok) {
                        await reloadLibrary();
                        const fresh = library.find((x) => x.id === it.id);
                        if (fresh) { selected = fresh; renderDetailView(fresh, tab); } else renderDetailView(it, tab);
                    } else {
                        b.disabled = false;
                        alert(r?.error === 'locked' ? t('detail.remove_passkey_locked') : t('detail.remove_passkey_error'));
                    }
                } catch (e) {
                    b.disabled = false;
                    alert(t('detail.remove_passkey_error'));
                }
            };
        });
        const fillBtn = detailEl.querySelector('#fillBtn');
        if (fillBtn) fillBtn.onclick = () => it.type === 'card' ? fillCardOnPage(it, tab) : fill(it, tab);
        const delBtn = detailEl.querySelector('#del');
        if (delBtn) delBtn.onclick = async () => {
            if (! confirm(t('detail.trash_confirm'))) return;
            delBtn.disabled = true;
            try {
                const r = await send({ type: 'trashItem', id: it.id });
                if (r?.ok) { selected = null; await reloadLibrary(); detailEl.innerHTML = `<div class="empty">${t('detail.empty')}</div>`; }
                else { delBtn.disabled = false; alert(r?.error === 'locked' ? t('detail.trash_locked') : t('detail.trash_error')); }
            } catch (e) {
                delBtn.disabled = false;
                alert(t('detail.trash_error'));
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
        const totpHint = it.hasTotp ? t('edit.totp_keep') : '';
        const loginFields = isLogin ? `
            <label class="fld">${t('edit.username')}</label><input id="e-user" value="${esc(it.username || '')}">
            <label class="fld">${t('edit.password')}</label><input id="e-pass" type="password" value="${esc(it.password || '')}">
            <label class="fld">${t('edit.website_url')}</label><input id="e-url" value="${urlVal}">
            <label class="fld">${t('edit.totp_secret')}${esc(totpHint)}</label><input id="e-totp" autocomplete="off">
            <label class="fld">${t('edit.note')}</label><textarea id="e-note" rows="3" style="resize:vertical">${esc(it.note || '')}</textarea>
        ` : `
            <label class="fld">${t('edit.password')}</label><input id="e-pass" type="password" value="${esc(it.password || '')}">
            <label class="fld">${t('edit.note')}</label><textarea id="e-note" rows="3" style="resize:vertical">${esc(it.note || '')}</textarea>
        `;
        detailEl.append(el(`<div>
            <div class="dhead">${avatar(it, true)}<div class="grow"><div class="dtitle">${esc(it.title)}</div><div class="dtype">${esc(TYPE[it.type] || it.type)}</div></div></div>
            ${loginFields}
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="primary" id="e-save" style="flex:1">${t('edit.save')}</button>
                <button id="e-cancel" style="flex:1">${t('edit.cancel')}</button>
            </div>
            <p class="err" id="e-err"></p>
        </div>`));
        const $ = (id) => document.getElementById(id);
        $('e-cancel').onclick = () => renderDetailView(it, tab);
        $('e-save').onclick = async () => {
            $('e-save').disabled = true; $('e-save').textContent = t('edit.saving');
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
                    $('e-save').disabled = false; $('e-save').textContent = t('edit.save');
                    $('e-err').textContent = r?.error === 'locked' ? t('edit.locked') : t('edit.error');
                }
            } catch (e) {
                $('e-save').disabled = false; $('e-save').textContent = t('edit.save');
                $('e-err').textContent = t('edit.error');
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
            if (! code || ! /^otpauth:\/\//i.test(code.data)) { alert(t('detail.scan_notfound')); return; }
            const r = await send({ type: 'updateItem', id: it.id, patch: { totp: code.data } });
            if (! r?.ok) { alert(r?.error === 'locked' ? t('detail.scan_locked') : t('detail.scan_error')); return; }
            await reloadLibrary();
            const fresh = library.find((x) => x.id === it.id);
            if (fresh) { selected = fresh; showDetail(fresh, tab); }
        } catch (e) { alert(t('detail.scan_capture_error')); }
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
    else detailEl.innerHTML = `<div class="empty">${t('detail.empty')}</div>`;
    paint('');
}

// --- Bookmarks view ---
async function renderBookmarks() {
    stopTotp();
    links.innerHTML = iconBtn('lock', 'lock', t('list.lock'));
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    app.innerHTML = '';

    // View switcher
    app.append(el(`<div class="seg" style="margin-bottom:10px">
        <button id="sw-pw">${t('nav.passwords')}</button>
        <button id="sw-bm" class="on">${t('nav.bookmarks')}</button>
    </div>`));
    document.getElementById('sw-pw').onclick = () => { mainView = 'passwords'; renderMain(); };
    document.getElementById('sw-bm').onclick = () => { mainView = 'bookmarks'; renderBookmarks(); };

    // Bookmarks are read-only here — the only action is a one-way import of the
    // browser's own bookmarks into the vault. Everything else is view/open only.
    app.append(el(`<div style="display:flex;gap:6px;margin-bottom:8px">
        <button class="primary" id="bm-import" style="flex:1;margin-top:0;padding:8px 10px;display:flex;align-items:center;justify-content:center;gap:5px">${icon('folder-plus', 14)} ${t('bm.import_browser')}</button>
    </div>`));

    // Search + filter chips
    app.append(el(`<div class="search" style="position:relative;margin-bottom:8px">${icon('magnifier', 16)}<input id="bm-q" placeholder="${esc(t('bm.search_ph'))}" style="padding-left:32px"></div>`));
    app.append(el(`<div class="chips" id="bm-chips" style="margin-bottom:8px"></div>`));
    app.append(el(`<div id="bm-crumbs" class="bm-crumbs"></div>`));
    app.append(el(`<div id="bm-msg" class="err" style="margin:0 0 6px"></div>`));
    app.append(el(`<ul id="bm-list" style="list-style:none;margin:0;padding:0;overflow-y:auto;max-height:320px"></ul>`));
    app.append(el(`<div id="bm-detail"></div>`));

    // Load data
    let bookmarks = [];
    let folders = [];
    let bmCwd = '';        // current folder id; '' = All (root)
    let bmFilter = 'all';  // all | favorites | readlater
    let bmQuery = '';

    const $ = (id) => document.getElementById(id);
    const listEl = () => $('bm-list');
    const detailEl = () => $('bm-detail');
    const setMsg = (t) => { const m = $('bm-msg'); if (m) m.textContent = t || ''; };
    const byId = (id) => folders.find((f) => f.id === id) || null;
    const childFolders = (pid) => folders.filter((f) => (f.parentId || '') === (pid || '')).sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    const bookmarksIn = (fid) => bookmarks.filter((b) => (fid ? b.folderId === fid : ! b.folderId)).sort((a, b) => (a.title || a.url || '').localeCompare(b.title || b.url || ''));

    function folderPath(id) {
        const out = []; const seen = new Set(); let cur = byId(id);
        while (cur && ! seen.has(cur.id)) { seen.add(cur.id); out.unshift(cur); cur = cur.parentId ? byId(cur.parentId) : null; }
        return out;
    }

    async function reload() { const d = await send({ type: 'bookmarks.list' }); bookmarks = d.bookmarks || []; folders = d.folders || []; }

    function openBm(b) {
        if (/^https?:\/\//i.test(b.url)) chrome.tabs.create({ url: b.url });
        else setMsg(t('bm.unsafe_url'));
    }

    function paintChips() {
        const chips = $('bm-chips'); if (! chips) return;
        const defs = [
            { id: 'all', label: `${icon('folder', 12)} ${t('bm.browse')}` },
            { id: 'favorites', label: `${icon('star', 12)} ${t('bm.favorites')}` },
            { id: 'readlater', label: `${icon('clock', 12)} ${t('bm.read_later')}` },
        ];
        chips.innerHTML = '';
        for (const d of defs) {
            const c = el(`<button class="chip${bmFilter === d.id ? ' on' : ''}" style="display:inline-flex;align-items:center;gap:3px">${d.label}</button>`);
            c.onclick = () => { bmFilter = d.id; paint(); };
            chips.append(c);
        }
    }

    function paintCrumbs() {
        const c = $('bm-crumbs'); if (! c) return;
        c.innerHTML = '';
        const home = el(`<button class="crumb">${icon('bookmark', 12)} ${t('bm.all_crumb')}</button>`);
        home.onclick = () => { bmCwd = ''; paint(); };
        c.append(home);
        for (const f of folderPath(bmCwd)) {
            c.append(el(`<span class="crumb-sep">${icon('chevron', 11)}</span>`));
            const b = el(`<button class="crumb">${esc(f.name)}</button>`);
            b.onclick = () => { bmCwd = f.id; paint(); };
            c.append(b);
        }
    }

    function folderRow(f) {
        const count = bookmarksIn(f.id).length + childFolders(f.id).length;
        const countLabel = t(count === 1 ? 'bm.item_count_one' : 'bm.item_count_other', { count });
        const li = el(`<li><div class="bmrow">
            <button class="main"><span class="av mono">${icon('folder', 15)}</span><span class="grow"><div class="t">${esc(f.name)}</div><div class="u">${countLabel}</div></span></button>
            <span class="chev">${icon('chevron', 14)}</span>
        </div></li>`);
        li.querySelector('.main').onclick = () => { bmCwd = f.id; paint(); };
        return li;
    }

    function bookmarkRow(b, showPath) {
        const path = showPath && b.folderId ? folderPath(b.folderId).map((f) => f.name).join(' / ') : '';
        const sub = path || b.url || '';
        const li = el(`<li><div class="bmrow">
            <button class="main"><span class="av mono">${icon('bookmark', 14)}</span><span class="grow"><div class="t">${esc(b.title || b.url || t('bm.untitled'))}</div><div class="u">${esc(sub)}</div></span>${b.favorite ? `<span class="star">${icon('star', 13)}</span>` : ''}${b.readLater ? `<span style="color:var(--muted)">${icon('clock', 13)}</span>` : ''}</button>
            <span class="chev">${icon('open', 13)}</span>
        </div></li>`);
        li.querySelector('.main').onclick = () => openBm(b);
        return li;
    }

    function paint() {
        setMsg('');
        paintChips();
        const browsing = bmFilter === 'all' && ! bmQuery.trim();
        const crumbs = $('bm-crumbs'); if (crumbs) crumbs.style.display = browsing ? '' : 'none';
        if (detailEl()) detailEl().innerHTML = '';
        const ul = listEl(); if (! ul) return;
        ul.innerHTML = '';
        if (browsing) {
            paintCrumbs();
            const subs = childFolders(bmCwd);
            const bms = bookmarksIn(bmCwd);
            for (const f of subs) ul.append(folderRow(f));
            for (const b of bms) ul.append(bookmarkRow(b, false));
            if (! subs.length && ! bms.length) ul.append(el(`<li class="muted">${t('bm.empty_folder')}</li>`));
        } else {
            const q = bmQuery.trim().toLowerCase();
            let items = bookmarks;
            if (bmFilter === 'favorites') items = items.filter((b) => b.favorite);
            else if (bmFilter === 'readlater') items = items.filter((b) => b.readLater);
            if (q) items = items.filter((b) => (b.title || '').toLowerCase().includes(q) || (b.url || '').toLowerCase().includes(q) || (b.tags || []).some((t) => t.toLowerCase().includes(q)));
            items = items.slice().sort((a, b) => (a.title || a.url || '').localeCompare(b.title || b.url || ''));
            for (const b of items) ul.append(bookmarkRow(b, true));
            if (! items.length) ul.append(el(`<li class="muted">${t('bm.empty_search')}</li>`));
        }
    }

    // One-way import of the browser's OWN bookmarks — the only write path (no
    // per-item create/edit/delete). Reads the browser tree, flattens it to
    // {title,url,path[]}, and hands it to the background to merge (folders rebuilt
    // from the path, http(s)-only, duplicates skipped).
    async function importFromBrowser() {
        const btn = $('bm-import');
        if (! chrome.bookmarks) { setMsg(t('bm.import_error')); return; }
        if (btn) btn.disabled = true;
        setMsg(t('bm.importing'));
        let tree;
        try { tree = await chrome.bookmarks.getTree(); }
        catch (e) { setMsg(t('bm.import_error')); if (btn) btn.disabled = false; return; }
        const items = [];
        const walk = (nodes, path) => {
            for (const n of nodes || []) {
                if (n.url) items.push({ title: n.title || '', url: n.url, path });
                if (n.children) walk(n.children, n.title ? [...path, n.title] : path);
            }
        };
        walk(tree, []);
        if (! items.length) { setMsg(t('bm.import_none')); if (btn) btn.disabled = false; return; }
        const r = await send({ type: 'bookmarks.importBrowser', items });
        if (btn) btn.disabled = false;
        if (r && typeof r.added === 'number') {
            bmFilter = 'all'; bmQuery = ''; bmCwd = '';
            const qEl = $('bm-q'); if (qEl) qEl.value = '';
            await reload(); paint();
            setMsg(t('bm.import_done', { added: r.added, skipped: r.skipped || 0 }));
        } else {
            setMsg(r?.error === 'locked' ? t('bm.import_locked') : t('bm.import_error'));
        }
    }

    // Wire up toolbar + search
    $('bm-import').onclick = () => importFromBrowser();
    $('bm-q').addEventListener('input', (e) => { bmQuery = e.target.value; paint(); });

    await reload();
    paint();
}

// --- Create a new login ---
function renderNew(prefill = {}) {
    stopTotp();
    links.innerHTML = iconBtn('back', 'back', t('new.back'));
    document.getElementById('back').onclick = () => render();
    app.innerHTML = '';
    app.append(el(`<div>
        <label class="fld">${t('new.title')}</label><input id="n-title">
        <label class="fld">${t('new.username')}</label><input id="n-user">
        <label class="fld">${t('new.password')}</label>
        <div class="prev" style="margin-top:4px"><input id="n-pass" type="text" class="grow" style="border:0;background:transparent;padding:0"><button class="ic" id="n-gen" title="${esc(t('new.generate'))}">${icon('refresh', 16)}</button><button class="ic" id="n-copy" title="${esc(t('new.copy'))}">${icon('clipboard', 16)}</button></div>
        <label class="fld">${t('new.website')}</label><input id="n-url">
        <button class="primary" id="n-save">${t('new.save')}</button>
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
        if (! login.title && ! login.username && ! login.url) { $('n-err').textContent = t('new.validation'); return; }
        $('n-save').disabled = true; $('n-save').textContent = t('new.saving');
        const r = await send({ type: 'createLogin', login });
        if (r?.ok) render();
        else { $('n-err').textContent = r?.error === 'locked' ? t('new.locked') : t('new.error'); $('n-save').disabled = false; $('n-save').textContent = t('new.save'); }
    };
    $('n-title').focus();
}

// --- Password generator ---
const gen = { mode: 'chars', length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false, words: 4, lang: 'en', sep: '-', capitalize: true, number: true };

function renderGen() {
    stopTotp();
    links.innerHTML = iconBtn('back', 'back', t('gen.back'));
    document.getElementById('back').onclick = () => render();
    app.innerHTML = '';
    const langOpts = GEN_LANGS.map((l) => `<option value="${l}">${l.toUpperCase()}</option>`).join('');
    app.append(el(`<div>
        <div class="prev"><span class="grow" id="prev"></span>
            <button class="ic" id="regen" title="${esc(t('gen.regenerate'))}">${icon('refresh', 16)}</button>
            <button class="ic" id="copy" title="${esc(t('gen.copy'))}">${icon('clipboard', 16)}</button>
        </div>
        <div class="seg"><button id="mChars" class="on">${t('gen.chars')}</button><button id="mWords">${t('gen.words')}</button></div>
        <div id="cChars">
            <label class="rng">${t('gen.length_label')}<span id="lenv"></span><input type="range" min="8" max="64" id="len"></label>
            <div class="opts">
                <label><input type="checkbox" id="upper">${t('gen.upper')}</label>
                <label><input type="checkbox" id="lower">${t('gen.lower')}</label>
                <label><input type="checkbox" id="digits">${t('gen.digits')}</label>
                <label><input type="checkbox" id="symbols">${t('gen.symbols')}</label>
                <label><input type="checkbox" id="similar">${t('gen.similar')}</label>
            </div>
        </div>
        <div id="cWords" style="display:none">
            <label class="rng">${t('gen.words_label')}<span id="wcv"></span><input type="range" min="3" max="8" id="wc"></label>
            <div class="grid2">
                <label>Language<select id="lang">${langOpts}</select></label>
                <label>Separator<select id="sep"><option value="-">-</option><option value=".">.</option><option value="_">_</option><option value="space">${t('gen.sep_space')}</option><option value="">${t('gen.sep_none')}</option></select></label>
            </div>
            <div class="opts"><label><input type="checkbox" id="cap">${t('gen.capitalize')}</label><label><input type="checkbox" id="num">${t('gen.add_number')}</label></div>
        </div>
        <button class="primary" id="use">${t('gen.copy_clipboard')}</button>
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
