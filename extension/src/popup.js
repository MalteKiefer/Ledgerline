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

async function renderList() {
    links.innerHTML = '<a id="lock">Lock</a>';
    document.getElementById('lock').onclick = async () => { await send({ type: 'lock' }); render(); };
    app.innerHTML = '';
    app.append(el(`<div>
        <input id="q" placeholder="Search logins…" autofocus>
        <ul id="list"></ul>
    </div>`));
    const tab = await activeTab();
    const host = tab ? hostOf(tab.url) : '';
    const q = document.getElementById('q');
    const listEl = document.getElementById('list');

    const TYPE = { login: 'Login', password: 'Password', card: 'Card', wifi: 'Wi-Fi', license: 'License', server: 'Server' };
    async function paint(query) {
        const r = await send({ type: 'search', query });
        const items = r.logins || [];
        // Current-site logins first; the background already sorts the rest A–Z.
        items.sort((a, b) => matchScore(b, host) - matchScore(a, host));
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
    function matchScore(lg, h) { return lg.type === 'login' && (lg.urls || []).some((u) => hostOf(/^https?:\/\//.test(u) ? u : 'https://' + u) === h || h.endsWith('.' + hostOf('https://' + u))) ? 1 : 0; }
    q.addEventListener('input', () => paint(q.value));
    paint('');
}

async function copyValue(it) {
    const val = it.password || it.username || it.title;
    if (! val) return;
    try { await navigator.clipboard.writeText(val); } catch (e) { /* ignore */ }
    window.close();
}

async function fill(login, tab) {
    if (! tab) return;
    try {
        await chrome.tabs.sendMessage(tab.id, { type: 'fill', login: { id: login.id, username: login.username, password: login.password, hasTotp: login.hasTotp } });
    } catch (e) { /* content script not present on this page */ }
    window.close();
}

render();
