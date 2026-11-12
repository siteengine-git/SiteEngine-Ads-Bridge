'use strict';
// Executes shipped JS with a minimal DOM/transport double. NOT browser acceptance.
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const code = fs.readFileSync(require('node:path').join(__dirname, '../includes/product-setup.js'), 'utf8');
let count = 0;
function ok(v) { assert.ok(v); count++; }
async function run() {
    const listeners = {}, values = { credentials_path: '/private/draft.json', spreadsheet_id: 'synthetic-sheet' };
    const form = { elements: Object.fromEntries(Object.entries(values).map(([k, v]) => [k, { value: v }])), addEventListener: (k, f) => listeners[k] = f };
    class Data {
        constructor(f) { this.v = new Map(f ? Object.entries(f.elements).map(([k, v]) => [k, v.value]) : []); }
        set(k, v) { this.v.set(k, v); } [Symbol.iterator]() { return this.v[Symbol.iterator](); }
    }
    const status = { textContent: '' }, output = { textContent: '' }, utilityStatus = { textContent: '' };
    const utility = { addEventListener: (_, f) => listeners.utility = f };
    const button = { disabled: false, dataset: { nonce: 'synthetic-nonce' }, addEventListener: (_, f) => listeners.click = f };
    let request, response = { data: { message: 'Read verified; write unverified.' } };
    vm.runInNewContext(code, { document: {
        querySelector: () => ({ closest: () => form }),
        getElementById: id => ({ 'seab-unsaved': status, 'seab-test-sheets': button, 'seab-sheets-result': output, 'seab-crm-requirements': utility, 'seab-crm-setup-status': utilityStatus }[id])
    }, FormData: Data, URLSearchParams, AbortController, setTimeout, clearTimeout, ajaxurl: '/admin-ajax.php',
    fetch: async (url, args) => { request = { url, args }; return { json: async () => response }; } });
    form.elements.spreadsheet_id.value = 'changed-draft'; listeners.input(); ok(status.textContent.includes('Unsaved'));
    let prevented = false; listeners.utility({ preventDefault: () => prevented = true }); ok(prevented && utilityStatus.textContent.includes('kept'));
    form.elements.spreadsheet_id.value = values.spreadsheet_id; listeners.input(); ok(status.textContent.includes('saved'));
    prevented = false; listeners.utility({ preventDefault: () => prevented = true }); ok(!prevented);
    await listeners.click(); ok(request.url === '/admin-ajax.php' && request.args.credentials === 'same-origin');
    ok(request.args.body.v.get('action') === 'seab_test_sheets' && request.args.body.v.get('_ajax_nonce') === 'synthetic-nonce');
    ok(request.args.body.v.get('credentials_path') === '/private/draft.json' && request.args.body.v.size === 4);
    ok(output.textContent === response.data.message && !button.disabled);
    response = {}; await listeners.click(); ok(output.textContent.includes('incomplete evidence'));
    response = { data: { message: '<script>untrusted</script>' } }; await listeners.click(); ok(output.textContent === '<script>untrusted</script>');
    console.log(`${count} PASS; shipped JS with DOM/HTTP doubles; no native browser claim`);
}
run().catch(e => { console.error(e); process.exitCode = 1; });
