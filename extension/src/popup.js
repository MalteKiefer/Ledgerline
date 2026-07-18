import { generate, GEN_LANGS } from './generator.js';

const app = document.getElementById('app');
const links = document.getElementById('links');
const send = (msg) => new Promise((r) => chrome.runtime.sendMessage(msg, r));

function el(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
function esc(s) { return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

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
    star: '<path d="M11.48 3.5a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>',
};
function icon(name, size = 18) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="${size}" height="${size}">${PATHS[name] || ''}</svg>`;
}
function iconBtn(id, name, title) { return `<button class="ic" id="${id}" title="${esc(title)}" aria-label="${esc(title)}">${icon(name)}</button>`; }

async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}
function hostOf(url) { try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return ''; } }

const TYPE = { login: 'Login', password: 'Password', card: 'Card', wifi: 'Wi-Fi', license: 'License', server: 'Server' };
let totpTimer = null;
function stopTotp() { if (totpTimer) { clearInterval(totpTimer); totpTimer = null; } }

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
let tfaSet = null; // domains known to support app 2FA (2fa.directory)

// A login with no stored TOTP whose site is known to support app 2FA.
function supports2fa(it) {
    if (! tfaSet || ! it || it.type !== 'login' || it.hasTotp) return false;
    for (const u of (it.urls || [])) {
        let d = hostOf(/^https?:\/\//.test(u) ? u : 'https://' + u);
        while (d && d.includes('.')) { if (tfaSet.has(d)) return true; d = d.slice(d.indexOf('.') + 1); }
    }
    return false;
}

async function renderMain() {
    filterFolder = ''; filterTag = ''; selected = null; showAll = false;
    links.innerHTML = iconBtn('gen', 'key', 'Generate password') + iconBtn('refresh', 'refresh', 'Refresh from server') + iconBtn('lock', 'lock', 'Lock');
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    document.getElementById('gen').onclick = () => renderGen();
    app.innerHTML = '';
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
        } else {
            if (it.username) rows.push(plain('Username', it.username));
            if (it.password) rows.push(secret('Password', it.password));
        }
        if (it.hasTotp) rows.push(`<div class="field totp"><div class="flabel">One-time code</div><div class="frow"><span class="fval code" id="totpCode">······</span><span class="remain" id="totpRemain"></span><button class="ic" id="totpCopy" title="Copy">${icon('clipboard', 16)}</button></div></div>`);
        for (const u of (it.urls || [])) rows.push(field('Website', `<a class="fval" href="${esc(/^https?:\/\//.test(u) ? u : 'https://' + u)}" target="_blank" rel="noopener noreferrer">${esc(u)}</a>`, `<button class="ic" data-open="${esc(u)}" title="Open">${icon('open', 16)}</button>` + copyBtn(u)));
        if (it.note) rows.push(field('Note', `<span class="fval" style="white-space:pre-wrap">${esc(it.note)}</span>`, ''));

        const tags = (it.tags || []).length ? `<div class="dtags">${it.tags.map((t) => `<span class="tg">#${esc(t)}</span>`).join('')}</div>` : '';
        const canFill = it.type === 'login' || (it.type === 'card' && it.number) || ! ! it.password;
        const fillLabel = it.type === 'card' ? 'Fill card on this page' : 'Fill on this page';
        detailEl.innerHTML = '';
        detailEl.append(el(`<div>
            <div class="dhead">${avatar(it, true)}<div class="grow"><div class="dtitle">${esc(it.title)}</div><div class="dtype">${esc(TYPE[it.type] || it.type)}</div></div></div>
            ${supports2fa(it) ? `<div class="tfahint">${icon('key', 16)}<span>This website offers two-factor authentication — add a one-time code to this login.</span></div>` : ''}
            ${rows.join('')}
            ${tags}
            ${canFill ? `<button class="fillbtn" id="fillBtn">${icon('login', 16)} ${esc(fillLabel)}</button>` : ''}
        </div>`));

        detailEl.querySelectorAll('[data-copy]').forEach((b) => b.onclick = () => navigator.clipboard.writeText(b.dataset.copy).catch(() => {}));
        detailEl.querySelectorAll('[data-open]').forEach((b) => b.onclick = () => { const u = b.dataset.open; chrome.tabs.create({ url: /^https?:\/\//.test(u) ? u : 'https://' + u }); });
        detailEl.querySelectorAll('[data-reveal]').forEach((b) => {
            const id = b.dataset.reveal; let shown = false;
            b.onclick = () => { shown = ! shown; detailEl.querySelector(`[data-sec="${id}"]`).textContent = shown ? secrets[id] : '••••••••••'; b.innerHTML = icon(shown ? 'eyeslash' : 'eye', 16); };
        });
        const fillBtn = detailEl.querySelector('#fillBtn');
        if (fillBtn) fillBtn.onclick = () => it.type === 'card' ? fillCardOnPage(it, tab) : fill(it, tab);

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

    const first = await send({ type: 'search', query: '' });
    library = first.logins || [];
    folders = (await send({ type: 'folders' })).folders || [];
    tfaSet = new Set((await send({ type: 'tfa' })).domains || []);
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

function matchScore(lg, h) { return lg.type === 'login' && (lg.urls || []).some((u) => hostOf(/^https?:\/\//.test(u) ? u : 'https://' + u) === h || h.endsWith('.' + hostOf('https://' + u))) ? 1 : 0; }

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
