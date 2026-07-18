// Inline autofill. Finds login fields, asks the background worker for matching
// logins for this site, and offers a small picker anchored to the field. Holds
// no secrets beyond the moment of filling; all matching happens in the worker.

const send = (msg) => new Promise((r) => { try { chrome.runtime.sendMessage(msg, r); } catch (e) { r(null); } });

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
    const proto = input instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
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

async function fillCode(login) {
    if (! login.hasTotp || ! login.id) return;
    const r = await send({ type: 'totp', id: login.id });
    if (! r?.ok || ! r.code) return;
    const otp = otpField();
    if (otp) { setValue(otp, r.code); otp.focus(); } else {
        try { await navigator.clipboard.writeText(r.code); notify('2FA code copied'); } catch (e) { /* clipboard blocked */ }
    }
}

async function doFill(login) {
    const pw = passwordField();
    const user = pw ? usernameFor(pw) : usernameField();
    if (user) setValue(user, login.username);
    if (pw) setValue(pw, login.password);
    (pw || user)?.focus();
    if (login.hasTotp) await fillCode(login);
}

// --- Inline picker UI (Shadow DOM so the page's CSS can't touch it) ---
let host = null;
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
        item.onclick = () => { onPick(lg); closePicker(); };
        box.append(item);
    }
    shadow.append(box);
    document.body.append(host);
}

// Small badge inside the username field that opens the picker.
function attachBadge(field, logins, onPick = doFill) {
    if (! field || field.dataset.llBadge) return;
    field.dataset.llBadge = '1';
    const show = async () => {
        const fresh = await send({ type: 'match', hostname: location.hostname });
        let list = fresh?.ok ? fresh.logins : logins;
        if (onPick === fillCode) list = list.filter((l) => l.hasTotp); // OTP field: only logins with a code
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

async function init() {
    const pw = passwordField();
    const otp = otpField();
    const user = pw ? (usernameFor(pw) || pw) : usernameField();
    if (! pw && ! user && ! otp) return;
    const res = await send({ type: 'match', hostname: location.hostname });
    if (! res?.ok || ! res.logins?.length) return;
    if (user) attachBadge(user, res.logins);
    if (pw && pw !== user) attachBadge(pw, res.logins);
    // A standalone 2FA screen: offer the code on the OTP field.
    if (otp && res.logins.some((l) => l.hasTotp)) attachBadge(otp, res.logins, fillCode);
}

document.addEventListener('click', (e) => { if (host && ! host.contains(e.target)) closePicker(); });
chrome.runtime.onMessage.addListener((msg, _s, sendResponse) => {
    if (msg?.type === 'fill' && msg.login) {
        const had = ! ! (passwordField() || usernameField());
        doFill(msg.login);
        sendResponse({ filled: had }); // popup falls back to opening the URL if false
    }
});

// Run now and again after SPA/late-rendered forms appear.
init();
let tries = 0;
const iv = setInterval(() => { if (++tries > 10 || document.querySelector('[data-ll-badge]')) { clearInterval(iv); return; } init(); }, 1200);
