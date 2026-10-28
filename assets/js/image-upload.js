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
    async function decodeForOptimization(blob) {
        if (typeof createImageBitmap === 'function') {
            try {
                const bitmap = await createImageBitmap(blob);
                return {source:bitmap, width:bitmap.width, height:bitmap.height, release:()=>bitmap.close()};
            } catch (_) { /* Try the browser's normal image decoder next. */ }
        }
        const url = URL.createObjectURL(blob), image = new Image();
        try {
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = () => reject(new Error('This photo could not be read. Please choose a valid JPG, PNG or WebP image.'));
                image.src = url;
            });
            return {source:image, width:image.naturalWidth, height:image.naturalHeight, release:()=>URL.revokeObjectURL(url)};
        } catch (error) { URL.revokeObjectURL(url); throw error; }
    }
    async function optimizeInBrowser(file) {
        // Inspect signatures rather than trusting the file extension. Animated
        // formats keep the server path so canvas never silently flattens them.
        const bytes = new Uint8Array(await file.arrayBuffer());
        let mime;
        if (bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255) mime = 'image/jpeg';
        else if (bytes[0] === 137 && String.fromCharCode(...bytes.slice(1, 4)) === 'PNG') {
            mime = 'image/png';
            const view = new DataView(bytes.buffer);
            for (let offset = 8; offset + 12 <= bytes.length;) {
                const length = view.getUint32(offset);
                const type = String.fromCharCode(...bytes.slice(offset + 4, offset + 8));
                if (type === 'acTL') return null;
                if (type === 'IEND') break;
                if (length > bytes.length - offset - 12) break;
                offset += length + 12;
            }
        } else if (String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' && String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') {
            if (String.fromCharCode(...bytes.slice(12, 16)) === 'VP8X' && (bytes[20] & 2)) return null;
            mime = 'image/webp';
        } else return null;
        const bitmap = await decodeForOptimization(new Blob([bytes], {type: mime}));
        const canvas = document.createElement('canvas');
        try {
            if (bitmap.width * bitmap.height > 80000000 || Math.max(bitmap.width, bitmap.height) > 20000) throw new Error('This image is too large to optimize. Please use a smaller photo.');
            let scale = Math.min(1, 2560 / Math.max(bitmap.width, bitmap.height));
            for (let attempt = 0; attempt < 8; attempt++) {
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                const context = canvas.getContext('2d');
                if (!context) return null;
                context.drawImage(bitmap.source, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, mime, Math.max(.65, .9 - attempt * .04)));
                // Keep the original image format and alpha; unsupported encoders
                // fall back to the existing server optimizer.
                if (!blob || blob.type !== mime) return null;
                if (blob.size > 0 && blob.size <= maxBytes) return new File([blob], file.name, {type: mime, lastModified: file.lastModified});
                scale *= Math.max(.25, Math.min(.8, Math.sqrt(maxBytes * .9 / blob.size)));
            }
            return null;
        } finally { bitmap.release(); canvas.width = canvas.height = 0; }
    }
    async function optimize(file) {
        if (!isLargeImage(file)) return file;
        if (prepared.has(file)) return prepared.get(file);
        const task = (async () => {
            pending++; status();
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), optimizationTimeoutMs);
            try {
                const local = await optimizeInBrowser(file);
                if (local) return local;
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
