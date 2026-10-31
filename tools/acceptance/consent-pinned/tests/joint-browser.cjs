const fs = require('node:fs'), vm = require('node:vm'), crypto = require('node:crypto'), assert = require('node:assert/strict');
const path = require('node:path');
// Reuse the retained VM boundary harness, not its unchanged runtime regression suite.
const definitions = fs.readFileSync(path.join(__dirname, 'browser.cjs'), 'utf8').split('(async () => {')[0];
const fixture = vm.runInNewContext(definitions + '; ({harness, ack, settle, tick});', { require, __dirname, Buffer, console, URLSearchParams });
const helper = fs.readFileSync(path.join(__dirname, '../tools/acceptance/consent-browser.js'), 'utf8');
const hash = s => crypto.createHash('sha256').update(s).digest('hex');
let checks = 0;
function check(ok, label) { assert.ok(ok, label); checks++; console.log('PASS ' + label); }
async function setup() {
    const h = fixture.harness(), timers = new Set();
    h.config.version = '0.6.1-rc.2'; h.window.location.origin = 'https://alma.siteengine.space'; h.window.location.href = 'https://alma.siteengine.space/fixture';
    h.window.document = h.document; h.window.crypto.subtle = crypto.webcrypto.subtle;
    h.window.setTimeout = (fn, ms) => { if (ms === 20000) { const t = { fn }; timers.add(t); return t; } return setTimeout(fn, ms); };
    h.window.clearTimeout = t => { if (timers.has(t)) timers.delete(t); else clearTimeout(t); };
    const fields = Object.fromEntries(['se_joint_marker','se_joint_case','se_consent_receipt_id'].map(k => [k, { value: '', setAttribute(k,v) { this[k] = v; } }]));
    const form = { getAttribute: () => '3', checkValidity: () => true, calls: 0, requestSubmit() { this.calls++; },
        querySelector(s) { if (s.startsWith('.ff-el-input')) return {}; return fields[s.match(/name="([^"]+)"/)?.[1]] || null; }, setAttribute() {} };
    h.document.querySelector = () => form; h.document.querySelectorAll = () => [form];
    h.config.formFields = { receipt_id: 'se_consent_receipt_id' };
    vm.runInNewContext(helper, { window: h.window, TextEncoder, URLSearchParams, Uint8Array, Date, Set });
    const a = { assignment: 'ALMA-JOINT-CONSENT-ADS-20261031', site: h.window.location.origin + '/', form_id: 3, marker: 'jct_' + 'a'.repeat(32), browser: { page_url: h.window.location.href, form_selector: 'form[data-form_id="3"]' } };
    const bytes = JSON.stringify(a), sha = hash(bytes), release = JSON.stringify({ status: 'RELEASED_J1_J6_J7', allocation_sha256: sha });
    const ops = await h.window.SiteEngineJointConsent.start(bytes, sha, release, hash(release));
    async function settleNext(revision) {
        await fixture.tick(); const request = h.requests.at(-1), payload = JSON.parse(new URLSearchParams(request.options.body).get('payload'));
        const ack = fixture.ack(payload); ack.data.continuity.revision = revision; ack.data.row_id = revision;
        ack.data.receipt_id = 'sc1_' + String(revision).repeat(60);
        await fixture.settle(request, ack); return payload;
    }
    const j = { assignment: a.assignment, marker: a.marker, allocation_sha256: sha, chain_id: 'b'.repeat(64), entries: {}, audit: {}, head: {} };
    async function admit(label, revision) {
        const s = h.api.getConsent(), id = 100 + revision;
        j.entries[label] = { id, response_sha256: 'c'.repeat(64) }; j.audit[revision] = { receipt_id: s.receipt_id }; j.head.revision = revision;
        const text = JSON.stringify(j); await ops.admit(label, id, text, hash(text));
    }
    return { h, ops, timers, form, fields, a, bytes, sha, release, j, settleNext, admit };
}
(async () => {
    const x = await setup(), { h, ops } = x, original = h.window.fetch;
    const grant = ops.grant(); await x.settleNext(1); await grant; ops.submit('A');
    assert.throws(() => ops.submit('A'), /repeat refused/); check(x.form.calls === 1, 'repeat submission refused before another vendor submit');
    const foreignJournal = JSON.stringify({ assignment:x.a.assignment, marker:x.a.marker, allocation_sha256:x.sha, chain_id:'b'.repeat(64), entries:{A:{id:101,response_sha256:'c'.repeat(64)}} });
    await assert.rejects(ops.admit('A',999,foreignJournal,hash(foreignJournal)), /submission ID differs/);
    check(h.requests.length === 1, 'foreign returned submission ID cannot be admitted');
    await x.admit('A', 1);
    const fault = await ops.beginWithdrawalFault();
    check(h.requests.length === 1 && fault.server_capture_requests === 0 && !h.api.getConsent().receipt_id, 'real rc.2 rejection is pending locally; exact capture transport held');
    const failed = await ops.failAcknowledgement();
    check(failed.status === 'failed' && !h.api.getConsent().consent.advertising && h.window.fetch === original, 'actual capture fails locally and fetch is positively restored');
    const retry = ops.retryWithdrawal(), retryPayload = await x.settleNext(2); await retry;
    check(retryPayload.event_id === fault.event_id && h.requests.length === 2, 'actual retryAudit retains same event and sends one request after restoration');
    ops.submit('B'); await x.admit('B', 2);
    const regrant = ops.regrant(); await x.settleNext(3); await regrant; ops.submit('C'); await x.admit('C', 3);
    check(h.api.getConsent().continuity_key === retryPayload.continuity_key && x.form.calls === 3 && h.requests.length === 3, 'one continuity key, three acknowledged requests and exactly A/B/C native submits');
    check(x.fields.se_joint_marker.value === x.a.marker && x.fields.se_joint_case.value === 'C', 'existing allocated marker/case fields populated without form definition edit');
    const resumeJournal = JSON.stringify(x.j);
    const resumed = await h.window.SiteEngineJointConsent.start(x.bytes,x.sha,x.release,hash(x.release),resumeJournal,hash(resumeJournal));
    assert.throws(() => resumed.submit('C'), /repeat refused/);
    check(h.requests.length === 3, 'pinned acknowledged-boundary resume preserves admission and refuses replay');
    check((await ops.close()).restoration === 'VERIFIED' && x.timers.size === 0, 'success removes all owned fetch fault/timer state');
    const y = await setup(), g = y.ops.grant(); await y.settleNext(1); await g; y.ops.submit('A'); await y.admit('A', 1);
    const originalY = y.h.window.fetch, savedReject = y.h.api.rejectNonEssential;
    y.h.api.rejectNonEssential = () => { throw Error('injected setup failure'); };
    await assert.rejects(y.ops.beginWithdrawalFault(), /injected setup failure/);
    y.h.api.rejectNonEssential = savedReject;
    check(y.h.window.fetch === originalY && y.timers.size === 0, 'exception during fault setup restores fetch and clears timeout');
    const z = await setup(), gz = z.ops.grant(); await z.settleNext(1); await gz; z.ops.submit('A'); await z.admit('A', 1);
    const originalZ = z.h.window.fetch; await z.ops.beginWithdrawalFault(); [...z.timers][0].fn(); await fixture.tick();
    check(z.h.window.fetch === originalZ && z.timers.size === 0, 'bounded timeout also removes injection');
    await assert.rejects(z.ops.retryWithdrawal(), /restore fault/); check(z.h.requests.length === 1, 'timeout stops rather than silently retrying a capture');
    const bad = JSON.stringify({ ...x.a, site: 'https://foreign.invalid/' });
    await assert.rejects(h.window.SiteEngineJointConsent.start(bad, hash(bad), x.release, hash(x.release)), /target/);
    check(h.requests.length === 3, 'foreign allocation refused without capture');
    console.log(`Browser tooling checks: ${checks} passed; actual embedded rc.2 JS, VM form/transport/storage only. No real browser/site execution.`);
})().catch(e => { console.error(e); process.exitCode = 1; });
