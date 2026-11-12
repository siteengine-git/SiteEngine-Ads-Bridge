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
        status.textContent = new URLSearchParams(new FormData(form)).toString() === original
            ? 'Displayed settings match the saved values.' : 'Unsaved changes. Use Save settings to retain them.';
    });
    const button = document.getElementById('seab-test-sheets'), output = document.getElementById('seab-sheets-result');
    button.addEventListener('click', async () => {
        button.disabled = true; output.textContent = 'Testing draft connection (read only)…';
        const body = new FormData();
        body.set('action', 'seab_test_sheets'); body.set('_ajax_nonce', button.dataset.nonce);
        for (const key of ['credentials_path', 'spreadsheet_id']) body.set(key, form.elements[key].value);
        const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 25000);
        try {
            const response = await fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
            const result = await response.json();
            output.textContent = typeof result.data?.message === 'string' ? result.data.message : 'Connection test returned incomplete evidence; no write access is established.';
        } catch (_) { output.textContent = 'Connection test did not complete. No export or row append was requested.'; }
        finally { clearTimeout(timer); button.disabled = false; }
    });
})();
