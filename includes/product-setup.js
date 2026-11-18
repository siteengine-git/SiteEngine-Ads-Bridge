/* Ordinary Setup: one save, explicit dirty state, bounded draft connection test. */
(() => {
    'use strict';
    const marker = document.querySelector('input[name="seab_tab"][value="product"]');
    if (!marker) return;
    const form = marker.closest('form'), status = document.getElementById('seab-unsaved');
    const original = new URLSearchParams(new FormData(form)).toString();
    const utility = document.getElementById('seab-crm-requirements');
    if (utility) utility.addEventListener('submit', event => {
        if (new URLSearchParams(new FormData(form)).toString() !== original) {
            event.preventDefault();
            document.getElementById('seab-crm-setup-status').textContent = 'Save your entered settings first; CRM provisioning reloads this page. Your unsaved values have been kept.';
        }
    });
    form.addEventListener('input', () => {
        const priorResult = document.getElementById('seab-sheets-result');
        if (priorResult) priorResult.textContent = 'Settings edited. Test the displayed values explicitly; no provider request was made.';
        status.textContent = new URLSearchParams(new FormData(form)).toString() === original
            ? 'Displayed settings match the saved values.' : 'Unsaved changes. Use Save settings to retain them.';
    });
    const button = document.getElementById('seab-test-sheets'), output = document.getElementById('seab-sheets-result');
    const select = document.getElementById('seab-select-credentials');
    const test = async (choose = false) => {
        if (choose && new URLSearchParams(new FormData(form)).toString() !== original) { output.textContent = 'Save your draft settings before switching credential source.'; return; }
        button.disabled = true; if (select) select.disabled = true; output.textContent = 'Testing draft connection (read only)...';
        const body = new FormData();
        body.set('action', 'seab_test_sheets'); body.set('_ajax_nonce', button.dataset.nonce);
        for (const key of ['credentials_path', 'spreadsheet_id']) body.set(key, form.elements[key].value);
        body.set('credential_test_source', document.getElementById('seab-credential-test-source')?.value || 'file');
        if (choose) body.set('select_source', '1');
        const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 25000);
        try {
            const response = await fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
            const result = await response.json();
            if (choose && result.success === true) { window.location.reload(); return; }
            output.textContent = typeof result.data?.message === 'string' ? result.data.message : 'Connection test returned incomplete evidence; no write access is established.';
        } catch (_) { output.textContent = 'Connection test/source selection did not complete. Reload to inspect the selected source before retrying. No export or row append was requested.'; }
        finally { clearTimeout(timer); button.disabled = false; if (select) select.disabled = false; }
    };
    document.getElementById('seab-credential-test-source')?.addEventListener('change', () => { output.textContent = 'Source to test changed; active source is unchanged. Test explicitly.'; });
    button.addEventListener('click', () => test(false));
    if (select) select.addEventListener('click', () => test(true));
})();
