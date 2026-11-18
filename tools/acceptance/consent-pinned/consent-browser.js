/* Private DevTools helper only. No network/site execution on load. No bearer values are logged. */
(function (window) {
    'use strict';
    async function digest(text) {
        return Array.from(new Uint8Array(await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text))))
            .map(v => v.toString(16).padStart(2, '0')).join('');
    }
    function demand(value, message) { if (!value) throw new Error('STOP: ' + message); }
    window.SiteEngineJointConsent = {
        async start(allocationBytes, allocationHash, releaseBytes, releaseHash, journalBytes, journalHash) {
            demand(await digest(allocationBytes) === allocationHash && await digest(releaseBytes) === releaseHash, 'independent allocation/release pins differ');
            const a = JSON.parse(allocationBytes), release = JSON.parse(releaseBytes);
            demand(a.assignment === 'ALMA-JOINT-CONSENT-ADS-20261031' && a.site === window.location.origin + '/'
                && a.site === 'https://alma.siteengine.space/' && Number.isSafeInteger(a.form_id) && a.form_id > 0
                && /^jct_[a-f0-9]{32}$/.test(a.marker) && release.status === 'RELEASED_J1_J6_J7'
                && release.allocation_sha256 === allocationHash, 'target/fixture release mismatch');
            const api = window.SiteEngineConsent, config = window.SEConsentConfig;
            demand(api && typeof api.saveConsent === 'function' && typeof api.retryAudit === 'function'
                && config && config.version === '0.6.1-rc.2' && window.navigator.locks, 'actual rc.2 APIs/Web Locks required');
            demand(a.browser && typeof a.browser.form_selector === 'string' && a.browser.form_selector.length > 0
                && a.browser.form_selector.length <= 256 && typeof a.browser.page_url === 'string'
                && a.browser.page_url.split('#')[0] === window.location.href.split('#')[0], 'allocated page/form selector differs');
            const form = window.document.querySelector(a.browser.form_selector);
            demand(form && Number(form.getAttribute('data-form_id')) === a.form_id, 'allocated Fluent Forms DOM context absent');
            const field = name => form.querySelector('[name="' + name + '"]');
            demand(field('se_joint_marker') && field('se_joint_case') && field('se_consent_receipt_id'), 'allocated hidden fields absent; no implicit form edits');
            let phase = 'fresh', bearer = null, fault = null, halted = false;
            const used = new Set(), admitted = new Set(), evidence = [];
            function sameChain(p) {
                demand(p && /^[a-f0-9]{64}$/.test(p.continuity_key || ''), 'durable continuity unavailable');
                if (bearer === null) bearer = p.continuity_key;
                demand(p.continuity_key === bearer, 'second browser/site chain refused');
            }
            async function wait(predicate, milliseconds) {
                const end = Date.now() + milliseconds;
                do { if (predicate()) return; await new Promise(resolve => window.setTimeout(resolve, 20)); } while (Date.now() < end);
                throw new Error('STOP: bounded browser wait expired');
            }
            function summary(p) { return { event_id: p.event_id, receipt_id: p.receipt_id, row_id: p.audit_row_id || p.row_id, revision: p.continuity && p.continuity.revision, audit_status: p.audit_status }; }
            async function acknowledged(event, revision) {
                await wait(() => { const s = api.getConsent(); return s && s.event_id === event && ['saved','failed'].includes(s.audit_status); }, 10000);
                const s = api.getConsent(); sameChain(s);
                demand(s.audit_status === 'saved' && s.event_acknowledged === true && s.receipt_id && s.audit_token
                    && s.continuity && s.continuity.revision === revision, 'ACK/revision not verified in browser; stop, no automatic retry');
                evidence.push(summary(s)); return summary(s);
            }
            async function restoreFault() {
                if (!fault) return { restoration: 'VERIFIED', fetch_override: false };
                const f = fault;
                window.clearTimeout(f.timer);
                if (f.reject) { f.reject(new Error('owned acknowledgement fault removed')); f.reject = null; }
                demand(window.fetch === f.wrapper, 'fetch owner changed; retain controls and close fixture browser, do not overwrite foreign wrapper');
                window.fetch = f.original;
                demand(window.fetch === f.original, 'fetch restoration readback failed');
                fault = null;
                return { restoration: 'VERIFIED', fetch_override: false, server_capture_requests: 0 };
            }
            function journalIdentity(j) {
                demand(j.assignment === a.assignment && j.marker === a.marker && j.allocation_sha256 === allocationHash
                    && !j.closed && !j.stopped && !j.pending && /^[a-f0-9]{64}$/.test(j.chain_id || ''), 'journal ownership/stop state differs');
            }
            const existing = api.getConsent();
            if (!existing) {
                const name = (config.cookieName || 'se_consent') + '_continuity_' + (config.continuityScope || 'site');
                const context = JSON.parse(window.localStorage.getItem(name) || 'null');
                demand(context && context.revision === 0 && context.token === '' && /^[a-f0-9]{64}$/.test(context.key || ''), 'fresh durable revision-0 context required; never clear existing identity');
                bearer = context.key;
            }
            if (existing) {
                demand(journalBytes && await digest(journalBytes) === journalHash, 'existing browser requires independently pinned admitted journal');
                const j = JSON.parse(journalBytes); journalIdentity(j); sameChain(existing);
                const row = existing.audit_row_id || existing.row_id;
                demand(existing.audit_status === 'saved' && j.audit && j.audit[row] && j.audit[row].receipt_id === existing.receipt_id
                    && j.head && j.head.revision === existing.continuity.revision, 'browser is not the admitted current capture');
                for (const label of ['A','B','C']) if (j.entries[label]) { used.add(label); admitted.add(label); phase = label; }
                demand(phase !== 'fresh' && existing.continuity.revision === { A: 1, B: 2, C: 3 }[phase], 'resume only at acknowledged/admitted A/B/C boundary');
            }
            const ops = {
                async grant() {
                    demand(!halted && phase === 'fresh', 'grant sequence already used'); phase = 'grant-pending';
                    const p = api.saveConsent({ analytics: false, advertising: true, personalization: false }, 'joint_grant');
                    try { const r = await acknowledged(p.event_id, 1); phase = 'A'; return r; } catch (e) { halted = true; throw e; }
                },
                async beginWithdrawalFault() {
                    demand(!halted && phase === 'A' && admitted.has('A') && !fault, 'A must be submitted/admitted before withdrawal');
                    const f = { original: window.fetch, event: null, reject: null, hit: false, timer: null };
                    f.wrapper = function (url, options) {
                        const body = options && typeof options.body === 'string' ? new URLSearchParams(options.body) : null;
                        const payload = body && body.get('action') === 'se_consent_save_receipt' ? JSON.parse(body.get('payload') || 'null') : null;
                        if (!payload || payload.event_id !== f.event) return f.original.apply(this, arguments);
                        demand(!f.hit && payload.continuity_key === bearer && payload.consent.advertising === false, 'capture fault scope/repeat mismatch');
                        f.hit = true;
                        return new Promise((resolve, reject) => { f.reject = reject; });
                    };
                    fault = f; window.fetch = f.wrapper;
                    f.timer = window.setTimeout(() => { halted = true; restoreFault().catch(e => window.console.error(e.message)); }, 20000);
                    try {
                        const p = api.rejectNonEssential(); f.event = p.event_id; sameChain(p);
                        await wait(() => f.hit, 2000);
                        const s = api.getConsent();
                        demand(s.event_id === f.event && s.audit_status === 'pending' && !s.receipt_id && !s.audit_token && !s.consent.advertising, 'pending local rejection has authority');
                        phase = 'withdrawal-pending'; return { event_id: f.event, status: 'pending', server_capture_requests: 0, local_advertising: false };
                    } catch (e) { halted = true; await restoreFault(); throw e; }
                },
                async failAcknowledgement() {
                    demand(!halted && fault && phase === 'withdrawal-pending', 'no owned pending capture');
                    try {
                        fault.reject(new Error('owned test: capture transport failed')); fault.reject = null;
                        await wait(() => api.getConsent().audit_status === 'failed', 2000);
                        const s = api.getConsent(); sameChain(s);
                        demand(!s.receipt_id && !s.audit_token && !s.consent.advertising, 'failed rejection acquired authority');
                        phase = 'withdrawal-failed';
                        return { event_id: s.event_id, status: 'failed', local_advertising: false, server_capture_requests: 0 };
                    } finally { await restoreFault(); }
                },
                async retryWithdrawal() {
                    demand(!halted && phase === 'withdrawal-failed' && !fault, 'restore fault before deliberate same-event retry');
                    const event = api.getConsent().event_id;
                    try { demand(await api.retryAudit() === true, 'same-event audit retry failed'); const r = await acknowledged(event, 2); phase = 'B'; return r; }
                    catch (e) { halted = true; throw e; }
                },
                async regrant() {
                    demand(!halted && phase === 'B' && admitted.has('B'), 'B must be submitted/admitted before regrant'); phase = 'regrant-pending';
                    const p = api.saveConsent({ analytics: false, advertising: true, personalization: true }, 'joint_regrant');
                    try { const r = await acknowledged(p.event_id, 3); phase = 'C'; return r; } catch (e) { halted = true; throw e; }
                },
                submit(label) {
                    demand(!halted && !fault && label === phase && !used.has(label) && ['A','B','C'].includes(label), 'submission label/order/repeat refused');
                    const s = api.getConsent(); sameChain(s);
                    demand(s.audit_status === 'saved' && field('se_consent_receipt_id').value === s.receipt_id, 'real hidden receipt echo absent/mismatched');
                    demand(form.checkValidity() && typeof form.requestSubmit === 'function', 'complete valid vendor fields first; no fallback submit');
                    field('se_joint_marker').value = a.marker; field('se_joint_case').value = label;
                    used.add(label); form.requestSubmit();
                    return { label, action: 'native form requestSubmit once; obtain ID from actual response and admit it explicitly', marker: a.marker };
                },
                async admit(label, exactResponseID, bytes, hash) {
                    demand(!halted && used.has(label) && label === phase && !admitted.has(label) && await digest(bytes) === hash, 'admission order/pin differs');
                    const j = JSON.parse(bytes); journalIdentity(j);
                    demand(Number.isSafeInteger(exactResponseID) && exactResponseID > 0 && j.entries[label] && j.entries[label].id === exactResponseID
                        && /^[a-f0-9]{64}$/.test(j.entries[label].response_sha256 || ''), 'exact returned/admitted submission ID differs');
                    const s = api.getConsent(); sameChain(s);
                    demand(j.audit[s.audit_row_id || s.row_id] && j.audit[s.audit_row_id || s.row_id].receipt_id === s.receipt_id
                        && j.head.revision === s.continuity.revision, 'admission/current capture binding differs');
                    admitted.add(label); return { label, entry_id: exactResponseID, admission: 'MATCHES_PINNED_JOURNAL' };
                },
                restoreFault,
                async close() { const r = await restoreFault(); halted = true; return { ...r, retained: 'browser continuity/evidence; no receipt/link/head deletion', captures: evidence }; }
            };
            return ops;
        }
    };
})(window);
