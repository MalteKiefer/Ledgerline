// Inline autofill. Finds login fields, asks the background worker for matching
// logins for this site, and offers a small picker anchored to the field. Holds
// no secrets beyond the moment of filling; all matching happens in the worker.

import { genPassword } from './generator';

const send = (msg) => new Promise((r) => { try { chrome.runtime.sendMessage(msg, r); } catch (e) { r(null); } });

// Generator config for the inline panel; persists within the page so the panel
// remembers the last config. genPassword itself is the shared one from
// generator.js (no duplicate implementation).
const genOpts = { mode: 'chars', length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false };

// Frame trust (defense-in-depth). The content script runs in the top frame only
// (manifest all_frames:false) to avoid leaking credentials into untrusted
// cross-origin embeds. These guards keep card autofill top/same-origin only,
// so the behaviour stays safe even if frame injection is ever re-enabled.
const IS_TOP = window.top === window.self;
function sameOriginAsTop() { try { return window.top.location.origin === location.origin; } catch (e) { return false; } }
const CARDS_ALLOWED = IS_TOP || sameOriginAsTop();

function passwordField() {
    return [...document.querySelectorAll('input[type=password]')].find(isVisible) || null;
}
function isVisible(el) {
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
}
// The username field: the visible text/email input just before the password in
// the same form (or document order).
function usernameFor(pw) {
    const scope = pw.form || document;
    const inputs = [...scope.querySelectorAll('input')].filter(isVisible);
    const idx = inputs.indexOf(pw);
    for (let i = idx - 1; i >= 0; i--) {
        const t = (inputs[i].type || 'text').toLowerCase();
        if (['text', 'email', 'tel', ''].includes(t)) return inputs[i];
    }
    return inputs.find((x) => /user|email|login/i.test(x.name + ' ' + x.id + ' ' + (x.autocomplete || ''))) || null;
}
// A standalone username/email field (login pages that ask for the identifier
// first and reveal the password on a later step, e.g. Tresorit, Google).
function usernameField() {
    return [...document.querySelectorAll('input')].filter(isVisible).find((i) => {
        const t = (i.type || 'text').toLowerCase();
        if (! ['text', 'email', 'tel', ''].includes(t)) return false;
        if ((i.autocomplete || '').toLowerCase() === 'one-time-code') return false;
        const hay = (i.name + ' ' + i.id + ' ' + (i.autocomplete || '') + ' ' + (i.getAttribute('aria-label') || '') + ' ' + (i.placeholder || '')).toLowerCase();
        return t === 'email' || (i.autocomplete || '').toLowerCase().includes('username') || /user|e-?mail|login|account/.test(hay);
    }) || null;
}

function setValue(input, value) {
    if (! input) return;
    const proto = input instanceof HTMLSelectElement ? HTMLSelectElement.prototype
        : input instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
// Set a value, matching a <select> option by value or visible text.
function setChoice(el, candidates) {
    if (! el) return;
    if (el.tagName === 'SELECT') {
        for (const c of candidates) {
            const o = [...el.options].find((o) => o.value === c || o.textContent.trim() === c);
            if (o) { setValue(el, o.value); return; }
        }
        return;
    }
    setValue(el, candidates[0]);
}

// A one-time-code / 2FA field, by autocomplete, name/label heuristics, or a
// short numeric input. The password field is never treated as an OTP field.
function otpField() {
    return [...document.querySelectorAll('input')].filter(isVisible).find((i) => {
        const t = (i.type || 'text').toLowerCase();
        if (! ['text', 'tel', 'number', ''].includes(t)) return false;
        const hay = (i.name + ' ' + i.id + ' ' + (i.autocomplete || '') + ' ' + (i.placeholder || '') + ' ' + (i.getAttribute('aria-label') || '')).toLowerCase();
        if ((i.autocomplete || '').toLowerCase() === 'one-time-code') return true;
        if (/otp|totp|2fa|mfa|one.?time|auth.?code|verification|security.?code|\btoken\b/.test(hay)) return true;
        return i.maxLength >= 4 && i.maxLength <= 8 && (i.inputMode === 'numeric' || t === 'tel' || t === 'number');
    }) || null;
}

async function notify(text) {
    const n = document.createElement('div');
    n.textContent = text;
    n.style.cssText = 'position:fixed;z-index:2147483647;bottom:16px;right:16px;background:#111827;color:#fff;padding:8px 12px;border-radius:8px;font:13px system-ui,sans-serif;box-shadow:0 6px 20px #0004;';
    document.body.append(n);
    setTimeout(() => n.remove(), 2500);
}

// Segmented one-time-code inputs: a run of visible single-character boxes (as
// on desec.io). Each digit goes into its own box.
function otpCells() {
    return [...document.querySelectorAll('input')].filter(isVisible).filter((i) => {
        const t = (i.type || 'text').toLowerCase();
        return ['text', 'tel', 'number', 'password', ''].includes(t) && i.maxLength === 1;
    });
}

async function fillCode(login) {
    if (! login.hasTotp || ! login.id) return;
    const r = await send({ type: 'totp', id: login.id });
    if (! r?.ok || ! r.code) return;
    const code = r.code;
    const cells = otpCells();
    if (cells.length >= code.length) {
        for (let i = 0; i < code.length; i++) setValue(cells[i], code[i]);
        cells[code.length - 1].focus();
        return;
    }
    const otp = otpField();
    if (otp) { setValue(otp, code); otp.focus(); return; }
    try { await navigator.clipboard.writeText(code); notify('2FA code copied'); } catch (e) { /* clipboard blocked */ }
}

// --- Credit-card autofill ---
function fieldByHay(re, tags = 'input') {
    return [...document.querySelectorAll(tags)].filter(isVisible).find((i) => {
        const hay = (i.name + ' ' + i.id + ' ' + (i.getAttribute('aria-label') || '') + ' ' + (i.placeholder || '') + ' ' + (i.autocomplete || '')).toLowerCase();
        return re.test(hay);
    }) || null;
}
function acField(token, tags = 'input,select') {
    return [...document.querySelectorAll(tags)].filter(isVisible)
        .find((i) => (i.autocomplete || '').toLowerCase().split(/\s+/).includes(token)) || null;
}
function ccNumberField() {
    return acField('cc-number', 'input') || fieldByHay(/card.?number|cardnum|ccnum|creditcard|cc-?num|kreditkart/);
}
function ccGroup() {
    return {
        number: ccNumberField(),
        name: acField('cc-name', 'input') || fieldByHay(/card.?holder|name.?on.?card|karteninhaber/),
        exp: acField('cc-exp'),
        month: acField('cc-exp-month'),
        year: acField('cc-exp-year'),
        csc: acField('cc-csc', 'input') || fieldByHay(/\bcvv\b|\bcvc\b|security.?code|card.?code|pr(u|ü)fnummer/),
    };
}
function cardMask(number) { const d = String(number || '').replace(/\D/g, ''); return d ? '•••• ' + d.slice(-4) : ''; }

// A single expiry field wants MM/YY on some sites and MM/YYYY on others (and a
// few want no separator). Read the wanted shape from placeholder / pattern /
// maxlength rather than guessing.
function expShape(el) {
    const hay = ((el.placeholder || '') + ' ' + (el.getAttribute('pattern') || '') + ' ' + (el.getAttribute('aria-label') || '')).toLowerCase();
    const ml = el.maxLength;
    const long = /y{4}|j{4}/.test(hay) || ml === 7 || ml === 6;
    const sep = hay.includes('/') ? '/' : hay.includes('-') ? '-' : (ml === 4 || ml === 6) ? '' : '/';
    return { long, sep };
}

async function fillCard(card) {
    const g = ccGroup();
    if (card.number && g.number) setValue(g.number, String(card.number).replace(/\s+/g, ''));
    if (card.cardholder && g.name) setValue(g.name, card.cardholder);
    const m = String(card.expiry || '').match(/(\d{1,2})\s*[/\-.]?\s*(\d{2,4})/);
    const mm = m ? m[1].padStart(2, '0') : '';
    const yr = m ? m[2] : '';
    const yy = yr.length === 4 ? yr.slice(2) : yr;
    const yyyy = yr.length === 2 ? '20' + yr : yr;
    if (g.exp && mm && yr) {
        const { long, sep } = expShape(g.exp);
        setValue(g.exp, mm + sep + (long ? yyyy : yy));
    } else if (g.exp && card.expiry) {
        setValue(g.exp, card.expiry);
    }
    if (g.month && mm) setChoice(g.month, [mm, String(+mm)]);
    if (g.year && yr) setChoice(g.year, [yyyy, yy]);
    if (card.cvv && g.csc) setValue(g.csc, card.cvv);
    (g.number || g.csc)?.focus?.();
}

// --- New-login suggestion & capture ---
let lastGenerated = null;
function passwordFields() { return [...document.querySelectorAll('input[type=password]')].filter(isVisible); }
// Does this password field belong to a sign-up / change-password form (so we
// should offer a fresh generated password rather than an existing login)?
function isNewPw(input) {
    if (! input) return false;
    const ac = (input.autocomplete || '').toLowerCase();
    if (ac.includes('new-password')) return true;
    if (ac.includes('current-password')) return false;
    const hay = (input.name + ' ' + input.id + ' ' + (input.getAttribute('aria-label') || '') + ' ' + (input.placeholder || '')).toLowerCase();
    if (/new|confirm|repeat|wiederhol|register|signup|sign-?up|create/.test(hay)) return true;
    return passwordFields().length >= 2; // two password boxes ⇒ sign-up / change form
}
// Fill every visible password field (main + confirm) with the value + copy it.
function fillGenerated(field, pw) {
    const fields = passwordFields();
    if (fields.length) for (const f of fields) setValue(f, pw);
    else if (field) setValue(field, pw);
    lastGenerated = pw;
    navigator.clipboard.writeText(pw).catch(() => {});
}

// Inline generator panel (Shadow DOM): shows the suggested password and lets the
// user tune length + character classes to match the site's rules before filling.
let genHost = null;
function closeGenPanel() { if (genHost) { genHost.remove(); genHost = null; } }
function openGenPanel(field) {
    closeGenPanel(); closePicker();
    suppress = true; setTimeout(() => { suppress = false; }, 700);
    genHost = document.createElement('div');
    genHost.style.cssText = 'position:absolute;z-index:2147483647;';
    const r = field.getBoundingClientRect();
    genHost.style.top = (window.scrollY + r.bottom + 4) + 'px';
    genHost.style.left = (window.scrollX + r.left) + 'px';
    const shadow = genHost.attachShadow({ mode: 'closed' });
    const cb = (k, label) => `<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-k="${k}"${genOpts[k] ? ' checked' : ''}>${label}</label>`;
    const box = document.createElement('div');
    box.innerHTML = `
      <div style="width:280px;background:#fff;color:#111827;border:1px solid #0000001a;border-radius:10px;box-shadow:0 8px 24px #0003;padding:12px;font:13px system-ui,sans-serif">
        <div style="display:flex;align-items:center;gap:6px">
          <span id="pw" style="flex:1;min-width:0;word-break:break-all;font-family:ui-monospace,monospace;font-size:13px;background:#0000000a;border-radius:6px;padding:7px 8px"></span>
          <button id="regen" title="Regenerate" style="border:0;background:transparent;cursor:pointer;font-size:15px;padding:4px">↻</button>
        </div>
        <label style="display:block;font-size:11px;color:#6b7280;margin-top:10px">Length: <span id="lenv"></span></label>
        <input id="len" type="range" min="8" max="64" value="${genOpts.length}" style="width:100%">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:6px;font-size:12px">
          ${cb('upper', 'A–Z')}${cb('lower', 'a–z')}${cb('digits', '0–9')}${cb('symbols', '!@#')}
        </div>
        <button id="use" style="width:100%;margin-top:10px;padding:8px;border:0;border-radius:8px;background:#111827;color:#fff;font-weight:600;cursor:pointer">Fill password</button>
      </div>`;
    shadow.append(box);
    document.body.append(genHost);
    const q = (s) => shadow.querySelector(s);
    let pw = genPassword(genOpts);
    const paint = () => { q('#pw').textContent = pw; q('#lenv').textContent = genOpts.length; };
    const regen = () => { pw = genPassword(genOpts); paint(); };
    paint();
    q('#regen').onclick = regen;
    q('#len').oninput = (e) => { genOpts.length = +e.target.value; regen(); };
    shadow.querySelectorAll('input[data-k]').forEach((el) => { el.onchange = (e) => { genOpts[e.target.dataset.k] = e.target.checked; regen(); }; });
    q('#use').onclick = () => { fillGenerated(field, pw); closeGenPanel(); field?.focus?.(); notify('Password filled & copied'); };
}
function attachGenBadge(field) {
    if (! field || field.dataset.llBadge) return;
    const item = { title: 'Suggest a password…', username: 'Configurable · shown before filling', __gen: true };
    attachBadge(field, [item], () => openGenPanel(field), async () => [item]);
}
document.addEventListener('click', (e) => { if (genHost && e.target !== genHost) closeGenPanel(); }, true);

async function doFill(login) {
    lastLogin = login; lastAt = Date.now();
    const pw = passwordField();
    const user = pw ? usernameFor(pw) : usernameField();
    if (user) setValue(user, login.username);
    if (pw) setValue(pw, login.password);
    (pw || user)?.focus();
    if (login.hasTotp) await fillCode(login);
}

// --- Inline picker UI (Shadow DOM so the page's CSS can't touch it) ---
let host = null;
// After a fill we refocus a field, which would retrigger its focus handler and
// reopen the picker (covering the page's own submit button). Suppress briefly.
let suppress = false;
// The last login the user picked, so a password field revealed on a later step
// (multi-step / SPA logins) can be completed automatically.
let lastLogin = null, lastAt = 0;
function closePicker() { if (host) { host.remove(); host = null; } }

function openPicker(anchor, logins, onPick = doFill) {
    closePicker();
    host = document.createElement('div');
    host.style.cssText = 'position:absolute;z-index:2147483647;';
    const r = anchor.getBoundingClientRect();
    host.style.top = (window.scrollY + r.bottom + 4) + 'px';
    host.style.left = (window.scrollX + r.left) + 'px';
    const shadow = host.attachShadow({ mode: 'closed' });
    const box = document.createElement('div');
    box.style.cssText = 'min-width:220px;max-width:320px;background:#fff;color:#111;border:1px solid #0003;border-radius:10px;box-shadow:0 8px 24px #0003;overflow:hidden;font:13px system-ui,sans-serif;';
    for (const lg of logins) {
        const item = document.createElement('button');
        item.style.cssText = 'display:flex;gap:8px;align-items:center;width:100%;padding:8px 10px;border:0;background:transparent;text-align:left;cursor:pointer;';
        item.onmouseenter = () => { item.style.background = '#0000000d'; };
        item.onmouseleave = () => { item.style.background = 'transparent'; };
        const av = document.createElement('span');
        av.style.cssText = 'width:24px;height:24px;border-radius:6px;background:#e5e7eb;color:#374151;display:flex;align-items:center;justify-content:center;font-weight:600;flex:none;';
        av.textContent = (lg.title || lg.username || '?').charAt(0).toUpperCase();
        const txt = document.createElement('span');
        const t = document.createElement('div'); t.style.fontWeight = '500'; t.textContent = lg.title || lg.username;
        const u = document.createElement('div'); u.style.cssText = 'font-size:11px;color:#9ca3af;'; u.textContent = lg.username;
        txt.append(t, u);
        item.append(av, txt);
        item.onclick = () => { suppress = true; closePicker(); onPick(lg); setTimeout(() => { suppress = false; }, 700); };
        box.append(item);
    }
    shadow.append(box);
    document.body.append(host);
}

// Small badge inside the username field that opens the picker.
function attachBadge(field, logins, onPick = doFill, fetchList = null) {
    if (! field || field.dataset.llBadge) return;
    field.dataset.llBadge = '1';
    const show = async () => {
        if (suppress) return;
        let list;
        if (fetchList) {
            list = await fetchList();
        } else {
            const fresh = await send({ type: 'match', hostname: location.hostname });
            list = fresh?.ok ? fresh.logins : logins;
            if (onPick === fillCode) list = list.filter((l) => l.hasTotp); // OTP field: only logins with a code
        }
        if (list && list.length) openPicker(field, list, onPick);
    };
    field.addEventListener('focus', show);
    field.addEventListener('click', (e) => { e.stopPropagation(); show(); });

    // Visible in-field icon so the user can see autofill is available.
    const icon = document.createElement('div');
    icon.textContent = 'L';
    icon.title = 'Ledgerline — fill';
    icon.style.cssText = 'position:absolute;z-index:2147483646;width:18px;height:18px;border-radius:5px;background:#111827;color:#fff;font:600 11px/18px system-ui,sans-serif;text-align:center;cursor:pointer;box-shadow:0 1px 3px #0004;';
    icon.addEventListener('mousedown', (e) => { e.preventDefault(); e.stopPropagation(); show(); });
    const place = () => {
        const r = field.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) { icon.style.display = 'none'; return; }
        icon.style.display = 'block';
        icon.style.top = (window.scrollY + r.top + (r.height - 18) / 2) + 'px';
        icon.style.left = (window.scrollX + r.right - 24) + 'px';
    };
    document.body.append(icon);
    place();
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    setInterval(place, 1000); // follow dynamic/SPA layouts
}

async function scan() {
    const pw = passwordField();
    const otp = otpField();
    const user = pw ? (usernameFor(pw) || pw) : usernameField();
    if (! pw && ! user && ! otp) return;
    const res = await send({ type: 'match', hostname: location.hostname });
    if (! res?.ok || ! res.logins?.length) return;
    if (user) attachBadge(user, res.logins);
    if (pw && pw !== user && ! isNewPw(pw)) {
        attachBadge(pw, res.logins);
        // Multi-step login: the password appeared after the identifier step. If
        // the user just picked a login, finish the fill without a second click.
        if (lastLogin && ! pw.value && Date.now() - lastAt < 90000) {
            setValue(pw, lastLogin.password);
            if (lastLogin.hasTotp) fillCode(lastLogin);
        }
    }
    // A standalone 2FA screen: offer the code on the OTP field.
    if (otp && res.logins.some((l) => l.hasTotp)) attachBadge(otp, res.logins, fillCode);
}

// Payment forms: offer stored cards on the card-number field (cards match any
// site, so no host filtering — the list comes from a dedicated card fetch).
// Offer a generated password on sign-up / change-password fields (independent
// of whether we have a matching login for this site).
function scanNewPw() {
    const pw = passwordField();
    if (pw && isNewPw(pw) && ! pw.dataset.llBadge) attachGenBadge(pw);
}

async function scanCards() {
    if (! CARDS_ALLOWED) return; // never offer cards inside an untrusted cross-origin frame
    const num = ccNumberField();
    if (! num || num.dataset.llBadge) return;
    const fetchCards = () => send({ type: 'cards' }).then((r) => (r?.ok ? (r.cards || []).map((c) => ({ ...c, username: cardMask(c.number) })) : []));
    const cards = await fetchCards();
    if (cards.length) attachBadge(num, cards, fillCard, fetchCards);
}

document.addEventListener('click', (e) => { if (host && ! host.contains(e.target)) closePicker(); });
chrome.runtime.onMessage.addListener((msg, _s, sendResponse) => {
    if (msg?.type === 'fill' && msg.login) {
        const had = ! ! (passwordField() || usernameField());
        doFill(msg.login);
        sendResponse({ filled: had }); // popup falls back to opening the URL if false
    } else if (msg?.type === 'fillCard' && msg.card) {
        const had = CARDS_ALLOWED && ! ! ccNumberField();
        if (had) fillCard(msg.card);
        sendResponse({ filled: had });
    } else if (msg?.type === 'passkey.pick' && Array.isArray(msg.candidates)) {
        openPasskeyPicker(msg.candidates).then((choice) => sendResponse(choice || {}));
        return true; // async response
    }
});

// Attach on load, then keep watching: login forms are frequently rendered late
// or in steps (identifier first, password after). A debounced observer re-scans
// as fields appear, so both the badge and the auto-fill catch up.
let _t = null;
const runScan = () => { clearTimeout(_t); _t = setTimeout(() => { scan(); scanCards(); scanNewPw(); }, 250); };
runScan();
const mo = new MutationObserver(runScan);
mo.observe(document.documentElement, { childList: true, subtree: true });
setTimeout(() => mo.disconnect(), 60000);

// Attach a badge directly to whatever field the user focuses. This covers the
// cases the light-DOM scan misses: fields inside an open shadow root (the game
// login on kingdoms.com) and dialogs opened long after the observer stopped.
// composedPath()[0] is the real focused node even across shadow boundaries.
async function onFocusField(input) {
    if (! input || input.tagName !== 'INPUT' || input.dataset.llBadge) return;
    const t = (input.type || 'text').toLowerCase();
    const ac = (input.autocomplete || '').toLowerCase().split(/\s+/);
    const hay = (input.name + ' ' + input.id + ' ' + (input.autocomplete || '') + ' ' + (input.getAttribute('aria-label') || '') + ' ' + (input.placeholder || '')).toLowerCase();

    if (CARDS_ALLOWED && (ac.includes('cc-number') || /card.?number|cardnum|ccnum|creditcard|cc-?num|kreditkart/.test(hay))) {
        const fetchCards = () => send({ type: 'cards' }).then((r) => (r?.ok ? (r.cards || []).map((c) => ({ ...c, username: cardMask(c.number) })) : []));
        const cards = await fetchCards();
        if (cards.length) attachBadge(input, cards, fillCard, fetchCards);
        return;
    }
    if (ac.includes('one-time-code') || /otp|totp|2fa|mfa|one.?time|auth.?code|verification|security.?code|\btoken\b/.test(hay)) {
        const res = await send({ type: 'match', hostname: location.hostname });
        if (res?.ok && res.logins.some((l) => l.hasTotp)) attachBadge(input, res.logins, fillCode);
        return;
    }
    if (t === 'password' && isNewPw(input)) { attachGenBadge(input); return; }
    if (['password', 'text', 'email', 'tel', ''].includes(t)) {
        const res = await send({ type: 'match', hostname: location.hostname });
        if (res?.ok && res.logins.length) attachBadge(input, res.logins);
    }
}

// --- Capture new credentials on submit and offer to save them ---
let savePromptHost = null;
function closeSavePrompt() { if (savePromptHost) { savePromptHost.remove(); savePromptHost = null; } }
function promptSave(cred) {
    closeSavePrompt();
    const host = location.hostname.replace(/^www\./, '');
    savePromptHost = document.createElement('div');
    savePromptHost.style.cssText = 'position:fixed;z-index:2147483647;bottom:16px;right:16px;';
    const shadow = savePromptHost.attachShadow({ mode: 'closed' });
    const wrap = document.createElement('div');
    const esc = (s) => String(s || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    wrap.innerHTML = `
      <div style="width:300px;background:#fff;color:#111827;border:1px solid #0000001a;border-radius:12px;box-shadow:0 12px 34px #0003;padding:14px;font:13px system-ui,sans-serif">
        <div style="display:flex;align-items:center;gap:8px;font-weight:600"><span style="width:20px;height:20px;border-radius:5px;background:#111827;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px">L</span> Save this login to Ledgerline?</div>
        <label style="display:block;font-size:11px;color:#6b7280;margin:10px 0 3px">Title</label>
        <input id="t" style="width:100%;box-sizing:border-box;padding:7px;border:1px solid #d1d5db;border-radius:8px;font:inherit" value="${esc(host)}">
        <div style="font-size:12px;color:#6b7280;margin-top:8px">${cred.username ? ('Username: ' + esc(cred.username)) : 'No username detected'}</div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button id="d" style="flex:1;padding:8px;border:0;border-radius:8px;background:#0000000d;cursor:pointer;font:inherit">Not now</button>
          <button id="s" style="flex:1;padding:8px;border:0;border-radius:8px;background:#111827;color:#fff;font-weight:600;cursor:pointer;font:inherit">Save</button>
        </div>
      </div>`;
    shadow.append(wrap);
    document.body.append(savePromptHost);
    wrap.querySelector('#d').onclick = closeSavePrompt;
    wrap.querySelector('#s').onclick = async () => {
        const title = wrap.querySelector('#t').value.trim() || host;
        const btn = wrap.querySelector('#s'); btn.textContent = 'Saving…'; btn.disabled = true;
        const r = await send({ type: 'createLogin', login: { title, username: cred.username, password: cred.password, url: cred.url } });
        closeSavePrompt();
        notify(r?.ok ? 'Saved to Ledgerline' : (r?.error === 'locked' ? 'Unlock Ledgerline to save' : 'Could not save'));
    };
    setTimeout(closeSavePrompt, 20000);
}
async function captureSubmit(form) {
    try {
        const pws = form ? [...form.querySelectorAll('input[type=password]')].filter(isVisible) : passwordFields();
        const pw = pws[0] || passwordField();
        if (! pw || ! pw.value) return;
        const user = usernameFor(pw) || usernameField();
        const cred = { url: location.origin + '/', username: user ? user.value.trim() : '', password: pw.value };
        const known = await send({ type: 'match', hostname: location.hostname });
        const exists = cred.username && known?.ok && (known.logins || []).some((l) => (l.username || '').toLowerCase() === cred.username.toLowerCase());
        if (exists) return; // already stored
        promptSave(cred);
    } catch (e) { /* ignore */ }
}
// Passkey chooser: when the SW has >1 stored passkey for the RP and cannot
// auto-select, it asks us to show a picker. We render it fixed/centered in the
// viewport so it is visible regardless of whether a focused input exists.
// The SW sends { type:'passkey.pick', candidates:[{credentialId,userName,userDisplayName,rpId}] }
// via chrome.tabs.sendMessage; we reply with { credentialId } or {} on cancel.
//
// Design invariants (review fixes):
//  F1 — picker is position:fixed, top:12vh, centered: always visible, no anchor needed.
//  F2 — MutationObserver closes over a local snapshot of host (capturedHost), not the
//        module-level variable; obs.disconnect() is called on every teardown path.
//  F3 — resolve/cancel via a closure-local settled flag; no shared module slot.
//        A new openPasskeyPicker tears down any existing picker first via closePicker().
function openPasskeyPickerFixed(candidates) {
    // Tear down any in-flight picker first (F3: no abandoned resolver).
    closePicker();

    return new Promise((resolve) => {
        let settled = false;
        const finish = (result) => {
            if (settled) return;
            settled = true;
            obs.disconnect(); // F2: always disconnect the observer
            closePicker();
            resolve(result);
        };

        // Build display items compatible with the picker rendering.
        const items = candidates.map((c) => ({
            title: c.userDisplayName || c.userName || c.rpId || 'Passkey',
            username: c.userName || c.rpId || '',
            __credentialId: c.credentialId,
        }));

        // F1: render fixed, centered — position is independent of any input element.
        closePicker();
        host = document.createElement('div');
        // position:fixed keeps it in-viewport regardless of page scroll/length.
        host.style.cssText = 'position:fixed;z-index:2147483647;top:12vh;left:50%;transform:translateX(-50%);';
        const shadow = host.attachShadow({ mode: 'closed' });
        const box = document.createElement('div');
        box.style.cssText = 'min-width:220px;max-width:320px;background:#fff;color:#111;border:1px solid #0003;border-radius:10px;box-shadow:0 8px 24px #0003;overflow:hidden;font:13px system-ui,sans-serif;';
        for (const lg of items) {
            const item = document.createElement('button');
            item.style.cssText = 'display:flex;gap:8px;align-items:center;width:100%;padding:8px 10px;border:0;background:transparent;text-align:left;cursor:pointer;';
            item.onmouseenter = () => { item.style.background = '#0000000d'; };
            item.onmouseleave = () => { item.style.background = 'transparent'; };
            const av = document.createElement('span');
            av.style.cssText = 'width:24px;height:24px;border-radius:6px;background:#e5e7eb;color:#374151;display:flex;align-items:center;justify-content:center;font-weight:600;flex:none;';
            av.textContent = (lg.title || '?').charAt(0).toUpperCase();
            const txt = document.createElement('span');
            const t = document.createElement('div'); t.style.fontWeight = '500'; t.textContent = lg.title;
            const u = document.createElement('div'); u.style.cssText = 'font-size:11px;color:#9ca3af;'; u.textContent = lg.username;
            txt.append(t, u);
            item.append(av, txt);
            item.onclick = () => { finish({ credentialId: lg.__credentialId }); };
            box.append(item);
        }
        shadow.append(box);
        document.body.append(host);

        // F2: capture a local snapshot so the observer doesn't close over the
        // module-level `host` variable which may be reassigned by a later picker.
        const capturedHost = host;

        // Resolve with {} if the picker element is removed from the DOM without a pick
        // (e.g. closePicker() called by a click-outside handler).
        const obs = new MutationObserver(() => {
            if (! document.contains(capturedHost)) finish({});
        });
        obs.observe(document.documentElement, { childList: true, subtree: true });
    });
}
// Named alias used by the message listener below (matches the old call site name).
const openPasskeyPicker = openPasskeyPickerFixed;

// Relay passkey messages from the MAIN-world shim to the background SW and back.
// The shim posts { __ll_pk:'req', id, kind, request, origin } on window; we
// forward to the SW as { type: kind, request, origin } and return the result.
// Security: origin is ALWAYS taken from location.origin (the content script's
// own trusted execution context) — e.data.origin from the page is ignored to
// prevent a forged-origin attack on the SW's rpId binding check.
window.addEventListener('message', async (e) => {
    if (e.source !== window || e.origin !== location.origin || ! e.data || e.data.__ll_pk !== 'req') return;
    let res;
    try { res = await chrome.runtime.sendMessage({ type: e.data.kind, request: e.data.request, origin: location.origin }); }
    catch (err) { res = { ok: false, error: String(err) }; }
    window.postMessage({ __ll_pk: 'res', id: e.data.id, ok: ! ! (res && res.ok), result: res && res.result, error: res && res.error }, location.origin);
});

document.addEventListener('submit', (e) => { if (e.target && e.target.tagName === 'FORM') captureSubmit(e.target); }, true);
document.addEventListener('focusin', (e) => {
    runScan();
    const real = (e.composedPath && e.composedPath()[0]) || e.target;
    onFocusField(real);
}, true);
