(function () {
    'use strict';
    const active = new WeakMap();
    const replay = new WeakSet();
    let replayDepth = 0;
    const text = value => String(value || '').replace(/<[^>]*>/g, '').replace(/&nbsp;|\u00a0|\u200b/g, ' ').trim();
    function fieldLabel(el) {
        if (el.dataset.warningLabel || el.dataset.veLabel) return el.dataset.warningLabel || el.dataset.veLabel;
        const label = el.labels && el.labels[0];
        return (label ? label.textContent : el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || el.dataset.field || el.dataset.veSection || el.dataset.veField || 'Content')
            .replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
    }
    function scopeLabel(el) {
        const scope = el.closest('[data-warning-section],[data-ve-item],section,.pgm-panel,.modal-content,.card,fieldset');
        if (!scope) return 'Page content';
        const heading = scope.querySelector('h1,h2,h3,h4,h5,legend,.modal-title');
        return scope.dataset.warningSection || text(heading && heading.textContent) || 'Page content';
    }
    function optional(el) {
        if (el.closest('[data-warning-optional="true"],[data-warning-active="false"],template,.ve-add-tile')) return true;
        if (el.disabled || el.closest('[hidden],.d-none')) return true;
        if (el.matches('input,textarea,select,.ql-editor') && el.getClientRects().length === 0 && !el.hasAttribute('data-warning-required')) return true;
        const label = fieldLabel(el);
        if (/\boptional\b/i.test(label)) return true;
        if (el.hasAttribute('data-warning-required')) return false;
        // Controls for display choices, links, and fallbacks are not authored content requirements.
        return /^(empty .*message|facebook|theme|colour|color|icon|letter|accent|short code|button 2|second button)/i.test(label)
            || /\b(optional|fallback|remove|delete|search|filter)\b/i.test(label)
            || /(?:_empty|_icon|_path|_link|_url|_enabled|_color|_colour)$/.test(el.dataset.veKey || el.name || el.dataset.field || '')
            || /^(checkbox|radio|hidden|password|submit|button|reset|color|search|range)$/.test(el.type || '');
    }
    function inspect(root) {
        const fields = [], nodes = new Map(), seen = new Set();
        if (!root || !root.querySelectorAll) return { fields, nodes, missing: [] };
        const select = selector => { const elements = Array.from(root.querySelectorAll(selector)); if (root.matches?.(selector)) elements.unshift(root); return elements; };
        const add = (el, value, kind, label) => {
            if (seen.has(el) || optional(el)) return;
            seen.add(el);
            const id = 'field-' + fields.length;
            const descriptor = { id, section: scopeLabel(el), label: label || fieldLabel(el), kind: kind || 'text', value: String(value || '') };
            fields.push(descriptor); nodes.set(id, el);
        };
        select('[data-ve-field],[data-ve-section],[data-warning-field],input,textarea,select,.ql-editor,[data-ve-pick="prog-name"]').forEach(el => {
            if (el.matches('input,textarea,select') && !el.name && !el.dataset.field && !el.hasAttribute('data-warning-field')) return;
            if (el.matches('input,textarea,select') && el.type === 'file') {
                // Uploads are optional unless explicitly designated as content requirements.
                if (el.required || el.hasAttribute('data-warning-required') || /^(photo|logo|dean_photo|hero|seal|image)$/i.test(el.name || el.dataset.field || '')) {
                    const area = el.closest('.dept-media-field,.mb-3,.col-lg-6,.col-md-6') || el.parentElement;
                    const existing = area.querySelector('img[src]:not([src=""])');
                    add(el, el.files && el.files.length || existing ? 'selected' : '', 'image');
                }
                return;
            }
            if (el.matches('textarea,input[type="hidden"]') && el.parentElement.querySelector('.ql-editor')) return;
            if (el.hasAttribute('data-ve-section') && el.querySelector('[data-ve-field]')) return;
            const value = el.hasAttribute('data-warning-value') ? el.getAttribute('data-warning-value') : el.matches('input,textarea,select') ? el.value : el.textContent;
            add(el, text(value), 'text', el.classList.contains('ql-editor') ? 'Description' : null);
        });
        select('[data-ve-img],[data-ve-program-image],[data-ve-basic-image],[data-warning-image]').forEach(el => {
            const image = el.matches('img,video') ? el : el.querySelector('img,video');
            const removed = el.style.opacity && Number(el.style.opacity) < .5;
            const background = el.style.backgroundImage || el.getAttribute('data-bg') || el.getAttribute('data-src') || el.ownerDocument.defaultView.getComputedStyle(el).backgroundImage;
            const value = !removed && ((image && (image.getAttribute('src') || image.querySelector('source')?.getAttribute('src'))) || (background && background !== 'none' ? background : ''));
            add(el, value || '', 'image', el.getAttribute('aria-label') || el.getAttribute('alt') || el.dataset.veLabel || 'Image');
        });
        select('[data-office-address]').forEach(el => {
            const value = text(el.textContent);
            add(el, /^(set|add|choose) office location$/i.test(value) ? '' : value, 'location', 'Office location');
        });
        // An untouched blank Add Program placeholder is a control, not published content.
        const missing = fields.filter(field => !text(field.value)).filter(field => {
            const el = nodes.get(field.id), draft = el.closest('[data-ve-program-card^="draft-"]');
            return !draft || draft.dataset.warningPlaceholder !== 'true' || Array.from(draft.querySelectorAll('[data-ve-field]')).some(n => text(n.textContent)) || draft.querySelector('img');
        });
        return { fields, nodes, missing };
    }
    function mark(report, focusId) {
        const docs = new Set();
        report.nodes.forEach(el => docs.add(el.ownerDocument));
        docs.forEach(doc => {
            if (!doc.getElementById('cms-missing-highlight-style')) {
                const style = doc.createElement('style'); style.id = 'cms-missing-highlight-style';
                style.textContent = '.cms-missing-field{outline:2px solid #d8a52c!important;outline-offset:4px;background-color:rgba(255,193,7,.07)!important}';
                doc.head.appendChild(style);
                const clear = event => event.target.closest?.('.cms-missing-field')?.classList.remove('cms-missing-field');
                doc.addEventListener('input', clear); doc.addEventListener('change', clear);
            }
            doc.querySelectorAll('.cms-missing-field').forEach(el => el.classList.remove('cms-missing-field'));
        });
        report.missing.forEach(field => report.nodes.get(field.id)?.classList.add('cms-missing-field'));
        if (focusId) {
            const target = report.nodes.get(focusId);
            if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); target.focus({ preventScroll: true }); }
        }
    }
    async function verify(report, csrf) {
        if (!csrf || !report.fields.length) return;
        try {
            const response = await fetch('/admin/action/content-completeness-handler.php', { method: 'POST', credentials: 'same-origin',
                body: new URLSearchParams({ csrf_token: csrf, fields: JSON.stringify(report.fields) }) });
            const data = await response.json();
            // This is an advisory check of current edits, never a replacement for save permissions or validation.
            if (response.ok && data.success && Array.isArray(data.missing)) {
                const applicable = new Set(report.missing.map(field => field.id));
                report.missing = data.missing.filter(field => applicable.has(field.id));
            }
        } catch (_) { /* Local inspection remains available if the advisory endpoint is unavailable. */ }
    }
    function review(options) {
        const root = options.root || document;
        if (active.has(root)) return active.get(root);
        const task = (async () => {
            const report = options.report || inspect(root);
            await verify(report, options.csrf);
            if (!report.missing.length) return true;
            return new Promise(resolve => {
                const doc = document, previous = doc.activeElement;
                const dialog = doc.createElement('dialog'); dialog.className = 'cms-completeness-dialog';
                dialog.setAttribute('aria-labelledby', 'cmsCompletenessTitle'); dialog.setAttribute('aria-describedby', 'cmsCompletenessMessage');
                const make = (tag, className, value) => { const el = doc.createElement(tag); el.className = className; if (value) el.textContent = value; return el; };
                const body = make('div', 'cms-completeness-body');
                const warning = make('span', 'cms-completeness-icon', '!'); warning.setAttribute('aria-hidden', 'true'); body.append(warning);
                const title = make('h2', '', 'Warning: Incomplete Information'); title.id = 'cmsCompletenessTitle';
                const description = make('p', '', 'Some information is missing. You can complete these details or publish the page anyway.'); description.id = 'cmsCompletenessMessage';
                body.append(title, description, make('strong', '', 'Missing Information'));
                const list = make('ul', 'cms-completeness-list'); let jump;
                report.missing.forEach(field => {
                    const item = make('li', ''), button = make('button', '', field.section + ' — ' + field.label + ' is missing.');
                    button.type = 'button'; button.addEventListener('click', () => { jump = field.id; dialog.close('editing'); });
                    item.append(button); list.append(item);
                });
                const actions = make('div', 'cms-completeness-actions');
                const keep = make('button', 'cms-completeness-keep', 'Keep Editing'); keep.type = 'button'; keep.autofocus = true;
                const publish = make('button', 'cms-completeness-publish', 'Publish Anyway'); publish.type = 'button';
                keep.addEventListener('click', () => dialog.close('editing'));
                publish.addEventListener('click', () => { publish.disabled = true; dialog.close('publish'); });
                actions.append(keep, publish); body.append(list, actions); dialog.append(body);
                dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close('editing'); } });
                dialog.addEventListener('close', () => {
                    const proceed = dialog.returnValue === 'publish'; dialog.remove();
                    if (!proceed) mark(report, jump || report.missing[0]?.id);
                    if (!jump && previous?.isConnected) previous.focus({ preventScroll: true });
                    resolve(proceed);
                }, { once: true });
                doc.body.append(dialog); dialog.showModal();
            });
        })().finally(() => active.delete(root));
        active.set(root, task); return task;
    }
    const csrf = () => document.querySelector('meta[name="cms-csrf"]')?.content || '';
    // Delegated interception also covers controls added dynamically by CMS panels.
    document.addEventListener('submit', async event => {
        if (replayDepth) return;
        const form = event.target;
        if (!(form.matches('form[data-content-publish]') || /^(publish|approve)/i.test(text(event.submitter?.textContent))) || replay.has(form)) { replay.delete(form); return; }
        const status = form.querySelector('[name="status"]');
        if (status && /^(draft|pending)$/.test(status.value)) return;
        const report = inspect(form); if (!report.missing.length) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (active.has(form)) return;
        if (await review({ root: form, report, csrf: csrf() })) {
            let override = form.querySelector('[name="allow_incomplete"]');
            if (!override) { override = document.createElement('input'); override.type = 'hidden'; override.name = 'allow_incomplete'; form.append(override); }
            override.value = '1';
            replay.add(form);
            replayDepth++; try { form.requestSubmit(event.submitter || undefined); } finally { replayDepth--; replay.delete(form); }
        }
    }, true);
    document.addEventListener('click', async event => {
        if (replayDepth) return;
        let button = event.target.closest('button[data-content-publish]');
        if (!button) { const candidate = event.target.closest('button'); if (candidate && /^(publish|approve)/i.test(text(candidate.textContent))) button = candidate; }
        if (!button || button.type === 'submit' || button.disabled || replay.has(button)) { if (button) replay.delete(button); return; }
        const root = button.closest('[data-warning-scope],.pgm-panel,form,.data-card,.dm-card,.card,[data-dept-id],[data-pid]') || button.parentElement;
        const report = inspect(root); if (!report.missing.length) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (active.has(root)) return;
        if (await review({ root, report, csrf: csrf() })) {
            replay.add(button); replayDepth++; try { button.click(); } finally { replayDepth--; replay.delete(button); }
        }
    }, true);
    window.ContentCompleteness = { inspect, review, mark };
})();
