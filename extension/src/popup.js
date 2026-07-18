import { generate, GEN_LANGS } from './generator.js';

const app = document.getElementById('app');
const links = document.getElementById('links');
const send = (msg) => new Promise((r) => chrome.runtime.sendMessage(msg, r));

function el(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
function esc(s) { return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}
function hostOf(url) { try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return ''; } }

const TYPE = { login: 'Login', password: 'Password', card: 'Card', wifi: 'Wi-Fi', license: 'License', server: 'Server' };

async function render() {
    const st = await send({ type: 'getState' });
    links.innerHTML = '';
    if (! st.paired) return renderPair();
    if (! st.unlocked) return renderUnlock();
    return renderList();
}

function renderPair() {
    app.innerHTML = '';
    app.append(el(`<div>
        <p class="hint">Open your Ledgerline profile, start a command-line/extension pairing and copy the code. Approve the device there after connecting.</p>
        <label>Server URL</label><input id="url" placeholder="https://home.example.com" value="https://">
        <label>Pairing code</label><input id="code" placeholder="paste code">
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
    links.innerHTML = '<a id="unpair">Unpair</a>';
    document.getElementById('unpair').onclick = async () => { await send({ type: 'unpair' }); render(); };
    app.innerHTML = '';
    app.append(el(`<div>
        <p class="hint">Enter your vault passphrase to unlock. It stays in this browser session only — never sent to the server.</p>
        <label>Vault passphrase</label><input id="pass" type="password">
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

// Left-column filters: folder ('' = all, '_none' = no folder, else id) and tag.
let filterFolder = '';
let filterTag = '';

async function renderList() {
    links.innerHTML = '<a id="gen">Generate</a><a id="refresh">Refresh</a><a id="lock">Lock</a>';
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    document.getElementById('gen').onclick = () => renderGen();
    app.innerHTML = '';
    app.append(el(`<div class="cols">
        <div class="side" id="side"></div>
        <div class="main">
            <input id="q" placeholder="Search…" autofocus>
            <ul id="list"></ul>
        </div>
    </div>`));
    const tab = await activeTab();
    const host = tab ? hostOf(tab.url) : '';
    const q = document.getElementById('q');
    const listEl = document.getElementById('list');
    const sideEl = document.getElementById('side');

    let library = []; // full set, drives the sidebar (independent of the search box)
    let folders = [];

    function paintSide() {
        const counts = {};
        for (const it of library) counts[it.folder || '_none'] = (counts[it.folder || '_none'] || 0) + 1;
        const tags = [...new Set(library.flatMap((it) => it.tags || []))].sort((a, b) => a.localeCompare(b));
        sideEl.innerHTML = '';
        const btn = (label, active, on, count) => {
            const b = el(`<button class="f${active ? ' on' : ''}"><span class="n">${esc(label)}</span>${count != null ? `<span class="c">${count}</span>` : ''}</button>`);
            b.onclick = on; return b;
        };
        sideEl.append(el('<div class="grp">Folders</div>'));
        sideEl.append(btn('All items', filterFolder === '', () => { filterFolder = ''; paintSide(); paint(q.value); }, library.length));
        for (const f of folders) sideEl.append(btn(f.name, filterFolder === f.id, () => { filterFolder = f.id; paintSide(); paint(q.value); }, counts[f.id] || 0));
        if (counts._none) sideEl.append(btn('No folder', filterFolder === '_none', () => { filterFolder = '_none'; paintSide(); paint(q.value); }, counts._none));
        if (tags.length) {
            sideEl.append(el('<div class="grp">Tags</div>'));
            const wrap = el('<div class="chips"></div>');
            for (const t of tags) {
                const c = el(`<button class="chip${filterTag === t ? ' on' : ''}">#${esc(t)}</button>`);
                c.onclick = () => { filterTag = filterTag === t ? '' : t; paintSide(); paint(q.value); };
                wrap.append(c);
            }
            sideEl.append(wrap);
        }
    }

    async function paint(query) {
        const r = await send({ type: 'search', query });
        let items = r.logins || [];
        if (filterFolder === '_none') items = items.filter((x) => ! x.folder);
        else if (filterFolder) items = items.filter((x) => x.folder === filterFolder);
        if (filterTag) items = items.filter((x) => (x.tags || []).includes(filterTag));
        // Current-site logins first; the rest stay A–Z from the background.
        items = items.slice().sort((a, b) => matchScore(b, host) - matchScore(a, host));
        listEl.innerHTML = '';
        if (! items.length) { listEl.append(el('<li class="muted">Nothing found</li>')); return; }
        for (const it of items) {
            const sub = it.username || TYPE[it.type] || it.type;
            const tags = (it.tags || []).map((t) => `<span class="tag">#${esc(t)}</span>`).join('');
            const li = el(`<li><button>
                <span class="av">${esc((it.title || it.username || '?')[0].toUpperCase())}</span>
                <span class="grow"><span class="t">${esc(it.title)}</span><br><span class="u">${esc(sub)}${tags}</span></span>
                <span class="badge">${esc(TYPE[it.type] || it.type)}</span>
            </button></li>`);
            li.querySelector('button').onclick = () => it.type === 'login' ? fill(it, tab) : copyValue(it);
            listEl.append(li);
        }
    }

    const first = await send({ type: 'search', query: '' });
    library = first.logins || [];
    folders = (await send({ type: 'folders' })).folders || [];
    paintSide();
    q.addEventListener('input', () => paint(q.value));
    document.getElementById('refresh').onclick = async () => {
        const rf = document.getElementById('refresh');
        rf.textContent = '…';
        await send({ type: 'refresh' });
        rf.textContent = 'Refresh';
        library = (await send({ type: 'search', query: '' })).logins || [];
        folders = (await send({ type: 'folders' })).folders || [];
        paintSide(); await paint(q.value);
    };
    paint('');
}

function matchScore(lg, h) { return lg.type === 'login' && (lg.urls || []).some((u) => hostOf(/^https?:\/\//.test(u) ? u : 'https://' + u) === h || h.endsWith('.' + hostOf('https://' + u))) ? 1 : 0; }

// --- Password generator view ---
const gen = { mode: 'chars', length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false, words: 4, lang: 'en', sep: '-', capitalize: true, number: true };

function renderGen() {
    links.innerHTML = '<a id="back">Back</a>';
    document.getElementById('back').onclick = () => render();
    app.innerHTML = '';
    const langOpts = GEN_LANGS.map((l) => `<option value="${l}">${l.toUpperCase()}</option>`).join('');
    app.append(el(`<div>
        <div class="prev"><span class="grow" id="prev"></span>
            <button class="iconbtn" id="regen" title="Regenerate">↻</button>
            <button class="iconbtn" id="copy" title="Copy">⧉</button>
        </div>
        <div class="seg">
            <button id="mChars" class="on">Characters</button>
            <button id="mWords">Memorable words</button>
        </div>
        <div id="cChars">
            <label class="hint">Length: <span id="lenv"></span></label>
            <input type="range" min="8" max="64" id="len">
            <div class="opts">
                <label><input type="checkbox" id="upper" checked>A-Z</label>
                <label><input type="checkbox" id="lower" checked>a-z</label>
                <label><input type="checkbox" id="digits" checked>0-9</label>
                <label><input type="checkbox" id="symbols" checked>!@#</label>
                <label class="full"><input type="checkbox" id="similar">Allow look-alike characters</label>
            </div>
        </div>
        <div id="cWords" style="display:none">
            <label class="hint">Words: <span id="wcv"></span></label>
            <input type="range" min="3" max="8" id="wc">
            <div class="opts">
                <label class="full">Language<select id="lang">${langOpts}</select></label>
                <label class="full">Separator<select id="sep">
                    <option value="-">-</option><option value=".">.</option><option value="_">_</option><option value="space">Space</option><option value="">None</option>
                </select></label>
                <label><input type="checkbox" id="cap" checked>Capitalize</label>
                <label><input type="checkbox" id="num" checked>Add number</label>
            </div>
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
    const copyPrev = async () => { try { await navigator.clipboard.writeText(prev.textContent); } catch (e) { /* ignore */ } };
    $('copy').onclick = copyPrev;
    $('use').onclick = async () => { await copyPrev(); window.close(); };
    syncMode(); regen();
}

async function copyValue(it) {
    const val = it.password || it.username || it.title;
    if (! val) return;
    try { await navigator.clipboard.writeText(val); } catch (e) { /* ignore */ }
    window.close();
}

async function fill(login, tab) {
    if (! tab) return;
    let filled = false;
    try {
        const r = await chrome.tabs.sendMessage(tab.id, { type: 'fill', login: { id: login.id, username: login.username, password: login.password, hasTotp: login.hasTotp } });
        filled = ! ! (r && r.filled);
    } catch (e) { /* no content script on this page */ }
    // Nothing to fill here → open the login's site (first of possibly many URLs).
    if (! filled) {
        const u = (login.urls || [])[0];
        if (u) chrome.tabs.create({ url: /^https?:\/\//.test(u) ? u : 'https://' + u });
    }
    window.close();
}

render();
