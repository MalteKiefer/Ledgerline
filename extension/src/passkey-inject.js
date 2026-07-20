// MAIN-world shim installed on every top frame. Intercepts PublicKeyCredential
// create/get and routes them to the Ledgerline extension (postMessage → content
// script → background SW). Falls through to the native implementation when
// Ledgerline declines or has no matching credential.
// If another passkey provider (e.g. 1Password) has locked navigator.credentials,
// the try/catch below catches the TypeError and the shim is inert — disable the
// other provider to use Ledgerline passkeys.
(() => {
    const nativeCreate = navigator.credentials.create.bind(navigator.credentials);
    const nativeGet = navigator.credentials.get.bind(navigator.credentials);
    let seq = 0;
    const pending = new Map();
    // Pending conditional requests keyed by requestId: { resolve, reject }.
    const conditionalPending = new Map();

    // Advertise conditional mediation support. Guard: if a native implementation
    // already returns true (future browser or other provider), honour it without
    // clobbering — we still offer our own inline suggestion on top.
    try {
        const _nativeCMA = typeof PublicKeyCredential.isConditionalMediationAvailable === 'function'
            ? PublicKeyCredential.isConditionalMediationAvailable.bind(PublicKeyCredential)
            : null;
        PublicKeyCredential.isConditionalMediationAvailable = async () => {
            if (_nativeCMA) { try { if (await _nativeCMA()) return true; } catch (_) {} }
            return true;
        };
    } catch (_) { /* non-writable — native impl present, leave it */ }

    window.addEventListener('message', (e) => {
        if (e.source !== window || ! e.data || e.data.__ll_pk !== 'res') return;
        // Conditional resolution: content script posts back with requestId.
        if (e.data.conditional) {
            const cp = conditionalPending.get(e.data.requestId);
            if (! cp) return;
            conditionalPending.delete(e.data.requestId);
            if (e.data.ok && e.data.result) cp.resolve(e.data.result);
            else {
                // F4: 'SW unavailable' and 'superseded' are transient/abort conditions;
                // hard signing failures are NotAllowedError.
                const errMsg = e.data.error || 'NotAllowedError';
                const isAbort = errMsg === 'SW unavailable' || errMsg === 'superseded';
                cp.reject(new DOMException(errMsg, isAbort ? 'AbortError' : 'NotAllowedError'));
            }
            return;
        }
        const p = pending.get(e.data.id); if (! p) return;
        pending.delete(e.data.id); p(e.data);
    });
    function ask(kind, request) {
        return new Promise((resolve) => {
            const id = ++seq; pending.set(id, resolve);
            window.postMessage({ __ll_pk: 'req', id, kind, request, origin: location.origin }, location.origin);
            setTimeout(() => {
                if (! pending.has(id)) return; // already resolved
                pending.delete(id);
                resolve({ ok: false, error: 'timeout' });
            }, 30000);
        });
    }
    function toBytes(v) { return v instanceof ArrayBuffer ? new Uint8Array(v) : (ArrayBuffer.isView(v) ? new Uint8Array(v.buffer, v.byteOffset, v.byteLength) : v); }
    function b64u(bytes) { return btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
    function unb64u(s) { const t = s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4); return Uint8Array.from(atob(t), (c) => c.charCodeAt(0)); }

    // Serialize a PublicKeyCredentialCreationOptions/RequestOptions to base64url-safe JSON.
    function serialize(o) {
        return JSON.parse(JSON.stringify(o, (k, v) => {
            if (v instanceof ArrayBuffer || ArrayBuffer.isView(v)) return { __b64u: b64u(toBytes(v)) };
            return v;
        }));
    }

    // Wrap in try/catch: if another provider (e.g. 1Password) has made
    // navigator.credentials non-writable, our shim simply does not install —
    // the native/OS passkey UI handles the ceremony. The user must disable the
    // other provider to use Ledgerline passkeys.
    try {
        navigator.credentials.create = async function (opts) {
            if (! opts || ! opts.publicKey) return nativeCreate(opts);
            const res = await ask('passkey.create', serialize(opts.publicKey));
            if (! res.ok) return nativeCreate(opts); // Ledgerline declined → native authenticator
            const r = res.result;
            const rawId = unb64u(r.credentialId);
            return {
                id: r.credentialId, rawId: rawId.buffer, type: 'public-key',
                authenticatorAttachment: 'platform',
                response: {
                    clientDataJSON: unb64u(r.clientDataJSON).buffer,
                    attestationObject: unb64u(r.attestationObject).buffer,
                    getTransports: () => r.transports || ['internal', 'hybrid'],
                },
                getClientExtensionResults: () => ({ credProps: { rk: true } }),
            };
        };
    } catch (_) { /* another provider locked credentials.create — shim inert */ }

    try {
        navigator.credentials.get = async function (opts) {
            if (! opts || ! opts.publicKey) return nativeGet(opts);

            // Conditional mediation: long-lived request resolved by inline picker.
            // We register a pending promise and notify the content script. The
            // content script will resolve it when the user picks a passkey from
            // the inline autofill suggestion. If opts.signal aborts, we reject.
            if (opts.mediation === 'conditional') {
                // F3: supersede any prior pending conditional requests (SPA re-registration).
                for (const [_oldId, cp] of conditionalPending) {
                    cp.reject(new DOMException('Superseded by new conditional request', 'AbortError'));
                }
                conditionalPending.clear();

                const requestId = ++seq;
                const promise = new Promise((resolve, reject) => {
                    conditionalPending.set(requestId, { resolve, reject });
                });
                window.postMessage({
                    __ll_pk: 'req',
                    kind: 'passkey.conditional',
                    requestId,
                    request: serialize(opts.publicKey),
                    origin: location.origin,
                }, location.origin);
                // Honor AbortSignal: if the page navigates away or calls abort(),
                // reject with AbortError and tell the content script to hide.
                if (opts.signal) {
                    opts.signal.addEventListener('abort', () => {
                        const cp = conditionalPending.get(requestId);
                        if (cp) {
                            conditionalPending.delete(requestId);
                            cp.reject(new DOMException('Conditional get aborted', 'AbortError'));
                        }
                        window.postMessage({ __ll_pk: 'req', kind: 'passkey.conditional.abort', requestId, origin: location.origin }, location.origin);
                    }, { once: true });
                }
                // Wrap the raw result into a PublicKeyCredential-like object once resolved.
                return promise.then((r) => ({
                    id: r.credentialId, rawId: unb64u(r.credentialId).buffer, type: 'public-key',
                    authenticatorAttachment: 'platform',
                    response: {
                        clientDataJSON: unb64u(r.clientDataJSON).buffer,
                        authenticatorData: unb64u(r.authenticatorData).buffer,
                        signature: unb64u(r.signature).buffer,
                        userHandle: r.userHandle ? unb64u(r.userHandle).buffer : null,
                    },
                    getClientExtensionResults: () => ({}),
                }));
            }

            // Normal (non-conditional) get: ask via modal picker.
            const res = await ask('passkey.get', serialize(opts.publicKey));
            if (! res.ok) return nativeGet(opts);
            const r = res.result;
            return {
                id: r.credentialId, rawId: unb64u(r.credentialId).buffer, type: 'public-key',
                authenticatorAttachment: 'platform',
                response: {
                    clientDataJSON: unb64u(r.clientDataJSON).buffer,
                    authenticatorData: unb64u(r.authenticatorData).buffer,
                    signature: unb64u(r.signature).buffer,
                    userHandle: r.userHandle ? unb64u(r.userHandle).buffer : null,
                },
                getClientExtensionResults: () => ({}),
            };
        };
    } catch (_) { /* another provider locked credentials.get — shim inert */ }
})();
