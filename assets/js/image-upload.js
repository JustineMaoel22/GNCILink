(function () {
    'use strict';
    if (!window.GNC_IMAGE_FRAMING_CONFIG?.admin) return;
    const maxBytes = 5 * 1024 * 1024;
    const optimizationTimeoutMs = 60000;
    const nativeFetch = window.fetch.bind(window);
    const prepared = new WeakMap();
    let pending = 0;
    let notice;
    function status() {
        if (!notice) {
            notice = document.createElement('div');
            notice.setAttribute('role', 'status');
            notice.style.cssText = 'position:fixed;bottom:20px;left:20px;z-index:2147483647;background:#094024;color:white;padding:12px 18px;border-radius:8px;font:14px system-ui;box-shadow:0 3px 15px #0003';
            document.body.appendChild(notice);
        }
        notice.hidden = pending === 0;
        notice.textContent = 'Optimizing photos to 5 MB…';
    }
    function isLargeImage(file) {
        return file.size > maxBytes && (/^image\//i.test(file.type) || /\.(jpe?g|png|webp|gif|svg)$/i.test(file.name));
    }
    async function optimize(file) {
        if (!isLargeImage(file)) return file;
        if (prepared.has(file)) return prepared.get(file);
        const task = (async () => {
            pending++; status();
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), optimizationTimeoutMs);
            try {
                const form = new FormData();
                form.set('image', file);
                form.set('csrf_token', window.GNC_IMAGE_FRAMING_CONFIG.csrf || '');
                const response = await nativeFetch('/admin/action/optimize-image.php', { method: 'POST', body: form, credentials: 'same-origin', signal: controller.signal });
                if (!response.ok) {
                    const result = await response.json().catch(() => ({}));
                    throw new Error(result.error || 'Could not optimize this photo. The maximum saved image size is 5 MB. Please choose a smaller image.');
                }
                const blob = await response.blob();
                if (!blob.size || blob.size > maxBytes || !/^image\//.test(blob.type)) throw new Error('The photo could not be optimized to 5 MB.');
                return new File([blob], file.name, { type: blob.type, lastModified: file.lastModified });
            } catch (error) {
                if (error.name === 'AbortError') throw new Error('Photo optimization timed out. Please try again or choose a smaller image.');
                throw error;
            } finally { clearTimeout(timeout); pending--; status(); }
        })();
        prepared.set(file, task);
        try { return await task; } catch (error) { prepared.delete(file); throw error; }
    }
    async function optimizeFiles(files) {
        const result = [];
        for (const file of files) result.push(await optimize(file));
        return result;
    }
    function report(error) {
        const message = /5\s*MB/i.test(error.message) ? error.message : error.message + ' The maximum saved image size is 5 MB. Please choose a smaller image.';
        if (window.AdminDialogs) window.AdminDialogs.alert(message, { title: 'Photo upload' });
        else window.alert(message);
    }
    // Run before framing, photo checks and each form's existing size validation.
    document.addEventListener('change', async event => {
        const input = event.target;
        if (input.tagName !== 'INPUT' || input.type !== 'file') return;
        const files = Array.from(input.files || []);
        if (!files.some(isLargeImage)) return;
        event.stopImmediatePropagation();
        input.disabled = true;
        try {
            const transfer = new DataTransfer();
            for (const file of await optimizeFiles(files)) transfer.items.add(file);
            input.files = transfer.files;
            input.disabled = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) { input.value = ''; report(error); }
        finally { input.disabled = false; }
    }, true);
    document.addEventListener('drop', async event => {
        const files = Array.from(event.dataTransfer?.files || []);
        if (!files.some(isLargeImage)) return;
        event.preventDefault(); event.stopImmediatePropagation();
        try {
            const transfer = new DataTransfer();
            for (const file of await optimizeFiles(files)) transfer.items.add(file);
            event.target.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer }));
        } catch (error) { report(error); }
    }, true);
    document.addEventListener('submit', event => {
        if (!pending) return;
        event.preventDefault(); event.stopImmediatePropagation();
        report(new Error('Please wait for your photos to finish optimizing, then save again.'));
    }, true);
    function hints() {
        document.querySelectorAll('input[type="file"][accept*="image"]').forEach(input => {
            if (input.hidden || input.style.display === 'none' || input.dataset.imageUploadHint) return;
            input.dataset.imageUploadHint = 'true';
            const hint = document.createElement('small');
            hint.className = 'form-text';
            hint.textContent = '5 MB maximum per image. Larger photos are optimized automatically.';
            input.insertAdjacentElement('afterend', hint);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hints, { once: true });
    else hints();
    new MutationObserver(hints).observe(document.documentElement, { childList: true, subtree: true });
    window.GNCImageUpload = { optimize, optimizeFiles, maxBytes };
})();
