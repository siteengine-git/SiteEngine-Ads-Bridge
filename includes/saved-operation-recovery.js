/* Existing exact-operation continuation only. No upload, preview, begin or new job. */
(() => {
    const button = document.getElementById('seab-resume-operation');
    if (!button) return;
    const output = document.getElementById('seab-resume-result');
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            for (let batch = 0; batch < 64; batch++) {
                const data = new FormData();
                data.set('action', 'seab_crm_import'); data.set('operation', 'continue'); data.set('_ajax_nonce', button.dataset.nonce);
                const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 25000);
                let result;
                try { result = await (await fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal })).json(); }
                finally { clearTimeout(timer); }
                if (!result.success) throw new Error(result.data?.message || 'Recovery failed; preserve saved evidence.');
                output.textContent = `${result.data.status}: ${result.data.cursor}/${result.data.total}. Original operation retained.`;
                if (result.data.status === 'complete') { output.textContent += ' Reload Diagnostics for fresh readback.'; return; }
                if (result.data.status !== 'running') throw new Error('Recovery stopped with uncertain evidence. Inspect before retrying.');
            }
            throw new Error('Bounded continuation ended. Saved progress is retained; inspect before resuming.');
        } catch (e) { output.textContent = e.message || 'Request outcome unknown. Inspect the saved operation before retrying.'; }
        // No automatic retry or re-enable after an uncertain operation.
    });
})();
