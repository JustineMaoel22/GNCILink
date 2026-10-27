/**
 * GNC Admin Panel - Content Editor: visual (in-page) editing engine
 *
 * The Content Editor shows the REAL website page in a frame and lets people edit it
 * where it appears: click a heading or paragraph to type, click a picture to replace
 * it, click a slide to change its background. A small floating toolbar appears next to
 * whatever is selected; nothing else is on screen.
 *
 * How it fits together
 *   - admin/content-edit.php prints the shell (top bar, canvas, dock) and window.VE_CONFIG.
 *   - The public pages print data-ve-* markers ONLY for a signed-in editor opening them
 *     with ?ce_preview=1 (see cePreviewActive() in admin-functions.php). Visitors never
 *     see the markers or any unpublished text.
 *   - This file binds those markers to the existing backends. Nothing new on the server:
 *       page-review-handler.php   page text: save draft / publish / submit / approve ...
 *       page-content-handler.php  slideshow, homepage president, administration people
 *   - Edits stay in the editor until Save draft / Publish is pressed, so unpublished work
 *     never reaches the live site by accident.
 */
(function () {
    'use strict';
    var C = window.VE_CONFIG;
    if (!C) return;                                   // not the visual editor screen

    // ══════════════════ tiny helpers ══════════════════
    var D = document;
    var MB = 1024 * 1024;
    function $(s, r) { return (r || D).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); }
    function h(tag, attrs, kids) {
        var el = D.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) return;
            if (k === 'class') el.className = v;
            else if (k === 'text') el.textContent = v;
            else if (k === 'on') Object.keys(v).forEach(function (ev) { el.addEventListener(ev, v[ev]); });
            else el.setAttribute(k, v === true ? '' : v);
        });
        [].concat(kids || []).forEach(function (k) {
            if (k === null || k === undefined || k === false) return;
            el.appendChild(typeof k === 'string' ? D.createTextNode(k) : k);
        });
        return el;
    }
    function icon(name) { return h('i', { class: 'bi bi-' + name, 'aria-hidden': 'true' }); }
    function clip(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
    function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }
    function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }

    // ══════════════════ Safe HTML (same allow-list the server uses) ══════════════════
    var DROP = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, SVG: 1, MATH: 1, HEAD: 1, TITLE: 1, META: 1, LINK: 1,
                 NOSCRIPT: 1, TEMPLATE: 1, FORM: 1, INPUT: 1, TEXTAREA: 1, SELECT: 1, BUTTON: 1, AUDIO: 1, VIDEO: 1, IMG: 1 };
    var KEEP = { P: 'p', H2: 'h2', H3: 'h3', H4: 'h4', BLOCKQUOTE: 'blockquote', UL: 'ul', OL: 'ol', LI: 'li', STRONG: 'strong', EM: 'em', U: 'u',
                 B: 'strong', I: 'em', DIV: 'p' };
    var BLOCKS = { p: 1, h2: 1, h3: 1, h4: 1, blockquote: 1, ul: 1, ol: 1 };
    var ALIGNABLE = { p: 1, h2: 1, h3: 1, h4: 1, blockquote: 1, li: 1 };

    function alignOf(el) {
        var m = /text-align\s*:\s*(left|center|right|justify)\b/i.exec(el.getAttribute('style') || '');
        var a = m ? m[1].toLowerCase() : (el.getAttribute('align') || '').toLowerCase();
        return (a === 'center' || a === 'right' || a === 'justify') ? a : '';
    }
    function safeHref(h) {
        h = (h || '').replace(/[\u0000-\u001F\u007F\s]+/g, '');
        if (!h) return '';
        return /^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(h) ? h : '';
    }
    function walk(src, dst, doc) {
        Array.prototype.forEach.call(src.childNodes, function (n) {
            if (n.nodeType === 3) { dst.appendChild(doc.createTextNode(n.nodeValue)); return; }
            if (n.nodeType !== 1) return;
            var name = n.nodeName.toUpperCase();
            if (DROP[name]) return;
            if (name === 'BR') { dst.appendChild(doc.createElement('br')); return; }
            if (name === 'A') {
                var href = safeHref(n.getAttribute('href'));
                if (!href) { walk(n, dst, doc); return; }
                var a = doc.createElement('a');
                a.setAttribute('href', href);
                if (/^https?:/i.test(href)) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); }
                walk(n, a, doc); dst.appendChild(a); return;
            }
            var tag = KEEP[name];
            if (!tag) { walk(n, dst, doc); return; }
            var el = doc.createElement(tag);
            if ((tag === 'p' && n.classList.contains('dept-offers-label')) || (tag === 'ul' && n.classList.contains('dept-offers-list'))) el.className = tag === 'p' ? 'dept-offers-label' : 'dept-offers-list';
            if (ALIGNABLE[tag]) { var al = alignOf(n); if (al) el.setAttribute('style', 'text-align:' + al); }
            walk(n, el, doc);
            dst.appendChild(el);
        });
    }
    /** HTML from the editor (or anywhere) -> clean, allow-listed HTML. */
    function cleanHtml(html) {
        var parsed = new DOMParser().parseFromString('<!doctype html><body>' + (html || ''), 'text/html');
        var out = document.implementation.createHTMLDocument('');
        var box = out.createElement('div');
        walk(parsed.body, box, out);
        // wrap loose text / inline runs at the top level in paragraphs
        var res = out.createElement('div'), run = null;
        Array.prototype.slice.call(box.childNodes).forEach(function (n) {
            var isBlock = n.nodeType === 1 && BLOCKS[n.nodeName.toLowerCase()];
            if (isBlock) { run = null; res.appendChild(n); return; }
            if (!run) { run = out.createElement('p'); res.appendChild(run); }
            run.appendChild(n);
        });
        var s = res.innerHTML;
        s = s.replace(/<p(?: style="[^"]*")?>(?:\s|<br>|&nbsp;)*<\/p>/gi, '');
        s = s.replace(/<(strong|em|u)>\s*<\/\1>/gi, '');
        return s.trim();
    }
    function plainToHtml(text) {
        var esc = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
        return text.replace(/\r\n?/g, '\n').trim().split(/\n{2,}/).map(function (p) {
            return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>';
        }).join('');
    }
    function richStorage(html) {
        var clean=cleanHtml(html);
        if(!/<(?!\/?(?:p|br)\b)[a-z]/i.test(clean)&&clean.indexOf('style=')===-1){
            var box=D.createElement('textarea');
            box.innerHTML=clean.replace(/<br\s*\/?>/gi,'\n').replace(/<\/p>\s*<p>/gi,'\n\n').replace(/<\/?p>/gi,'');
            return box.value.trim();
        }
        return clean;
    }
    function countWords(html) {
        var t = (html || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').trim();
        return t ? t.split(/\s+/).length : 0;
    }
    window.__ceCleanHtml = cleanHtml; // exposed for tests

    // ══════════════════ notifications ══════════════════
    var toastBox = $('#ceToasts');
    function toast(message, type) {
        if (!toastBox) return;
        if(C.requiresApproval && /Submitted for Super Admin approval|Draft saved\. It is not live/i.test(message))type='ok';
        if(C.requiresApproval && type!=='error' && /Published|changes are live|now live/i.test(message)) message='Submitted for Super Admin approval. Published content is unchanged.';
        var t = h('div', { class: 'ce-toast ce-toast-' + (type === 'error' ? 'error' : 'ok'), role: type === 'error' ? 'alert' : 'status' }, [
            icon(type === 'error' ? 'exclamation-octagon-fill' : 'check-circle-fill'),
            h('span', { text: message }),
            h('button', { type: 'button', class: 'ce-toast-x', 'aria-label': 'Dismiss', text: '×' })
        ]);
        toastBox.appendChild(t);
        var kill = function () { if (t.parentNode) t.parentNode.removeChild(t); };
        t.querySelector('button').addEventListener('click', kill);
        setTimeout(kill, type === 'error' ? 9000 : 4500);
    }

    // ══════════════════ talking to the existing backends ══════════════════
    /** POST (multipart) to an existing handler. Always resolves to {ok, ...response}. */
    function send(url, fields, files) {
        var fd = new FormData();
        fd.append('csrf_token', C.csrf);
        if (S.publishing && S.allowIncomplete) fd.append('allow_incomplete', '1');
        Object.keys(fields || {}).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]); });
        Object.keys(files || {}).forEach(function (k) { if (files[k]) fd.append(k, files[k], files[k].name); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (res) {
            return res.json().catch(function () {
                if (res.status === 413) return { error: 'The upload exceeds the hosting request limit. Images must be at most 5 MB each. Upload fewer images at once or ask your hosting provider to increase the request limit.' };
                return { error: 'The server sent an unexpected reply (code ' + res.status + '). Please reload the page and try again.' };
            }).then(function (j) {
                if(j.submitted){j.success=false;j.error=j.message;}
                j.ok = res.ok && j.success !== false && !j.error;
                if (!j.ok && !j.error) j.error = 'Something went wrong. Please try again.';
                return j;
            });
        }, function (error) {
            console.error('Upload request failed:',error);
            if(error && error.code==='IMAGE_UPLOAD_PREPARATION')return {ok:false,error:error.message};
            return { ok: false, error: 'We could not reach the server. Please check your internet connection and try again.' };
        });
    }
    function sendContent(fields, files) { return send(C.contentHandler, fields, files); }
    function sendReview(fields) { return send(C.reviewHandler, fields); }

    // Existing-image positioning follows the same preview / Publish lifecycle as text and uploads.
    var imagePositions = {};
    var textPlacements = {}, textLayout = null;
    function textPlacementCount() { return Object.keys(textPlacements).reduce(function(n,key){return n+Object.keys(textPlacements[key]).length;},0); }
    function displayPlacementCount() { return imagePositionCount()+textPlacementCount(); }
    function applyTextPlacements() { if(S.win && S.win.GNCTextLayout)S.win.GNCTextLayout.setChanges(textPlacements); }
    function stageTextPlacement(key,device,value,original) {
        var entry=textPlacements[key] && textPlacements[key][device], base=entry?entry.base:original;
        if(same(base,value)){if(textPlacements[key]){delete textPlacements[key][device];if(!Object.keys(textPlacements[key]).length)delete textPlacements[key];}}
        else {textPlacements[key]=textPlacements[key]||{};textPlacements[key][device]={base:base,value:value};}
        applyTextPlacements();chrome();
    }
    async function publishTextPlacements() {
        if(!textPlacementCount())return;
        var changes={};Object.keys(textPlacements).forEach(function(key){changes[key]={};Object.keys(textPlacements[key]).forEach(function(device){changes[key][device]=textPlacements[key][device].value;});});
        var result=await send('/admin/action/text-layout-handler.php',{route:S.url,changes:JSON.stringify(changes)});
        if(!result.ok)throw new Error(result.error||'Could not save text placement.');
        if(S.win.GNCTextLayout)S.win.GNCTextLayout.acceptChanges(textPlacements);
        textPlacements={};
    }
    function imagePositionCount() { return Object.keys(imagePositions).length; }
    function previewImagePosition(path, value) {
        [window, S.win].forEach(function (win) {
            if (win && win.GNCImageFraming) win.GNCImageFraming.set(path, value);
        });
    }
    window.VEImageFraming = {
        stage: function (path, value, original) {
            if (!path) throw new Error('Choose an image stored on this website.');
            var base = imagePositions[path] ? imagePositions[path].base : original;
            if (same(base, value)) delete imagePositions[path];
            else imagePositions[path] = { base: base, value: value };
            previewImagePosition(path, value);
            chrome();
        }
    };
    async function publishImagePositions() {
        for (var path of Object.keys(imagePositions)) {
            var entry = imagePositions[path];
            var result = await send('/admin/action/image-framing-handler.php', { path: path, framing: JSON.stringify(entry.value) });
            if (!result.ok) throw new Error(result.error || 'Could not publish image position.');
            if (imagePositions[path] === entry) delete imagePositions[path];
        }
    }
    function discardImagePositions() {
        Object.keys(imagePositions).forEach(function (path) { previewImagePosition(path, imagePositions[path].base); });
        imagePositions = {};
        textPlacements={};applyTextPlacements();
    }

    // ══════════════════ editor state ══════════════════
    var S = {
        frame: $('#veFrame'), stage: $('#veStage'), doc: null, win: null,
        sel: null, adapter: null, device: 'desktop', fallback: false,
        busy: false, scrollKeep: null, loadCount: 0, hoverEl: null
    };
    var editorFeedback = null, drafts = null;
    function unsavedPageChanges() { return drafts && drafts.supported() ? drafts.unsaved() : S.adapter && S.adapter.dirtyCount() > 0; }
    var tb = $('#veTb'), pop = $('#vePop'), statusEl = $('#veStatus'), loader = $('#veLoader');
    var btnSecondary = $('#veSecondary'), btnPrimary = $('#vePrimary');
    var fileInput = $('#veFile');

    // ══════════════════ file picker ══════════════════
    function pickFile(accept, cb, personPhoto, uploadTarget, preserveImage) {
        fileInput.value = '';
        if (window.GNCImageFraming) window.GNCImageFraming.setUploadTarget(fileInput, uploadTarget || (S.sel && S.sel.el));
        if (personPhoto) fileInput.setAttribute('data-photo-check', '');
        else fileInput.removeAttribute('data-photo-check');
        if(preserveImage)fileInput.setAttribute('data-no-image-framing','');else fileInput.removeAttribute('data-no-image-framing');
        fileInput.setAttribute('accept', accept || 'image/*');
        fileInput.onchange = function () { var f = fileInput.files && fileInput.files[0]; if (f) cb(f); };
        fileInput.click();
    }
    function pickPersonPhoto(cb, uploadTarget) {
        pickFile('image/jpeg,image/png,image/webp', cb, true, uploadTarget);
    }
    function previewUrl(file) { var url=URL.createObjectURL(file);if(window.GNCEditorDrafts)window.GNCEditorDrafts.trackUrl(url,file);return url; }

    // ══════════════════ popover (small forms next to the toolbar) ══════════════════
    var popOnClose = null;
    function closePop() {
        if (pop.hidden) return;
        pop.hidden = true; pop.innerHTML = '';
        var cb = popOnClose; popOnClose = null; if (cb) cb();
    }
    /** Open the popover near (x, y) in page coordinates. */
    function openPop(content, x, y, onClose, wide) {
        closePop();
        pop.innerHTML = ''; pop.appendChild(content);
        pop.classList.toggle('ve-pop-wide', !!wide);
        pop.hidden = false; popOnClose = onClose || null;
        var w = pop.offsetWidth, hgt = pop.offsetHeight;
        pop.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        pop.style.top = Math.max(8, Math.min(y, window.innerHeight - hgt - 8)) + 'px';
        var first = pop.querySelector('input,textarea,select'); if (first) first.focus();
    }
    function popBelowToolbar(content, onClose, wide) {
        var r = tb.getBoundingClientRect();
        openPop(content, r.left, r.bottom + 8, onClose, wide);
    }
    function confirmPop(title, message, confirmLabel, onConfirm, danger) {
        var cancel = h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Cancel', on: { click: closePop } });
        var confirm = h('button', { type: 'button', class: 'btn btn-sm ' + (danger === false ? 'btn-gnc' : 'btn-outline-danger'), text: confirmLabel || 'Continue', on: { click: function () { closePop(); onConfirm(); } } });
        var box = h('div', { class: 've-pop-form' }, [
            h('strong', { class: 've-pop-label', text: title }),
            h('p', { class: 've-pop-hint', text: message }),
            h('div', { class: 've-pop-row' }, [cancel, confirm])
        ]);
        popBelowToolbar(box, null, true);
        confirm.focus();
    }
    function confirmPublish(message, onConfirm, options) {
        options = options || {};
        if(C.requiresApproval && drafts && drafts.supported() && !(options.root && options.root.ownerDocument !== D)) {
            window.AdminDialogs.confirm('Submit these changes for Super Admin approval? The published page will stay unchanged.', {title:'Submit for approval',confirmText:'Submit'}).then(async function(ok){
                if(!ok){if(options.cancel)options.cancel();return;}
                try{await run('Submitting…',async function(){var result=await drafts.submit();toast(result.message || 'Submitted for approval.','ok');});}
                catch(error){toast(error.message,'error');}
                if(options.cancel)options.cancel();
            });
            return;
        }
        if (D.querySelector('.ve-publish-dialog') || (typeof S !== 'undefined' && (S.busy || S.completenessPending))) { if (options.cancel) options.cancel(); return; }
        S.allowIncomplete = false;
        var reviewRoot = options.root || (typeof S !== 'undefined' && S.doc);
        if (window.ContentCompleteness && reviewRoot) {
            var report = window.ContentCompleteness.inspect(reviewRoot, { includeOptional: true });
            if (report.missing.length) {
                S.completenessPending = true;
                window.ContentCompleteness.review({ root: reviewRoot, report: report, csrf: options.csrf || C.csrf }).then(function (proceed) {
                    S.completenessPending = false;
                    if (proceed) { S.allowIncomplete = true; onConfirm(); } else if (options.cancel) options.cancel();
                }).catch(function () { S.completenessPending = false; if (options.cancel) options.cancel(); toast('Could not inspect the page. Please try again.', 'error'); });
                return;
            }
        }
        var previousFocus = D.activeElement;
        var dialog = h('dialog', { class: 've-publish-dialog', 'aria-labelledby': 'vePublishTitle', 'aria-describedby': 'vePublishMessage' });
        var cancel = h('button', { type: 'button', class: 've-publish-cancel', text: 'Keep editing', autofocus: true,
            on: { click: function () { dialog.close(); } } });
        var publish = h('button', { type: 'button', class: 've-publish-accept', text: 'Publish changes',
            on: { click: function () { dialog.close('publish'); } } });
        dialog.appendChild(h('div', { class: 've-publish-content' }, [
            h('span', { class: 've-publish-icon', 'aria-hidden': 'true' }, [icon('cloud-upload')]),
            h('h2', { id: 'vePublishTitle', text: 'Ready to publish?' }),
            h('p', { id: 'vePublishMessage', text: 'Publish your changes to the live website?' }),
            h('div', { class: 've-publish-actions' }, [cancel, publish])
        ]));
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog) return;
            var rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        });
        dialog.addEventListener('close', function () {
            var confirmed = dialog.returnValue === 'publish';
            dialog.remove();
            if (previousFocus && previousFocus.isConnected) previousFocus.focus({ preventScroll: true });
            if (confirmed) onConfirm(); else if (options.cancel) options.cancel();
        }, { once: true });
        D.body.appendChild(dialog);
        dialog.showModal();
    }
    window.ContentEditorWorkflow = {
        confirm: function (options) {
            options = options || {};
            return new Promise(function (resolve) {
                confirmPublish('Publish your changes to the live website?', function () { resolve(true); }, { root: options.root, csrf: options.csrf, cancel: function () { resolve(false); } });
            });
        },
        notify: function (message, error) {
            var draft = /\b(draft|unpublished|pending|approval|submitted)\b/i.test(message);
            var saved = /\b(saved|published|updated|created)\b/i.test(message);
            var approval = /\b(pending|approval|submitted)\b/i.test(message);
            toast(error ? message : approval ? message : draft ? 'Draft saved. Your changes are not live yet.' : saved ? 'Published. Your changes are live now.' : message, error ? 'error' : 'ok');
        }
    };
    document.addEventListener('mousedown', function (e) {
        if (!pop.hidden && !pop.contains(e.target) && !tb.contains(e.target)) closePop();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePop(); });
    /** A one-field popover form. */
    function askText(opts) {
        var input = h(opts.multiline ? 'textarea' : 'input', { class: 'form-control form-control-sm', type: 'text', value: opts.value || '', placeholder: opts.placeholder || '', rows: 3, maxlength: opts.max || 500 });
        if (opts.multiline) input.value = opts.value || '';
        var go = function () { var v = input.value.trim(); if (opts.required && !v) { input.focus(); return; } closePop(); opts.done(v); };
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !opts.multiline) { e.preventDefault(); go(); } });
        var row = h('div', { class: 've-pop-row' }, [
            h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: opts.cta || 'Apply', on: { click: go } })
        ].concat(opts.extra || []));
        var form = h('div', { class: 've-pop-form' }, [h('label', { class: 've-pop-label', text: opts.label }), input, opts.hint ? h('small', { class: 've-pop-hint', text: opts.hint }) : null, row]);
        if (opts.anchor) {
            var anchor = opts.anchor.getBoundingClientRect();
            var offset = opts.anchor.ownerDocument === D ? {left:0,top:0} : S.frame.getBoundingClientRect();
            openPop(form, offset.left + anchor.left, offset.top + anchor.bottom + 8);
        } else popBelowToolbar(form);
        return input;
    }

    // ══════════════════ the canvas: the real page, loaded in a frame ══════════════════
    var PLAIN = (function () {
        var d = D.createElement('div'); d.setAttribute('contenteditable', 'plaintext-only');
        return d.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';
    })();

    // Everything that makes the page "editable" is added to the page's own DOM here, at runtime.
    var FRAME_CSS = [
        '[class*="reveal"]{opacity:1!important;transform:none!important;visibility:visible!important}',
        '.timeline-dot.reveal-scale{transform:translateX(-50%)!important}',
        '@media(max-width:768px){.timeline-dot.reveal-scale{transform:none!important}}',
        '.gnc-backtop,#gncBackTop,.gnc-scroll-progress,#gnc-intro,.chatbot,[id*="chatbot"],#gncbot{display:none!important}',
        '.ve-hover{outline:2px dashed rgba(211,166,58,.95)!important;outline-offset:4px!important}',
        '.ve-selected{outline:2px solid #1f7a3d!important;outline-offset:4px!important}',
        '.ve-drag-handle{position:absolute;top:8px;right:8px;z-index:8;display:flex;align-items:center;gap:5px;padding:7px 10px;border:1px solid #b9cec0;border-radius:8px;background:#fff;color:#245b36;font:600 12px system-ui;cursor:grab;touch-action:none;box-shadow:0 2px 8px #0002}',
        '.ve-drag-handle:focus-visible{outline:3px solid #d3a63a;outline-offset:3px}',
        '.ve-drag-source{opacity:.3!important;outline:3px solid #1f7a3d!important;outline-offset:4px!important}',
        '.ve-drag-preview{position:fixed!important;left:0;top:0;z-index:2147483000;pointer-events:none!important;will-change:transform;filter:drop-shadow(0 18px 18px #0b422850)}',
        '.ve-drag-preview *{pointer-events:none!important}',
        '.ve-drag-preview>div{transform:scale(1) rotate(0);transform-origin:center;transition:transform .16s ease,opacity .16s ease;opacity:.85}',
        '.ve-drag-preview.is-lifted>div{transform:scale(1.035) rotate(-1deg);opacity:1}',
        '.ve-drag-preview .ve-drag-handle{display:none!important}',
        '@media(prefers-reduced-motion:reduce){.ve-drag-preview>div{transition:none!important}.ve-drag-preview.is-lifted>div{transform:none}}',
        '.ve-drag-target{outline:3px dashed #d3a63a!important;outline-offset:5px!important}',
        '.ve-dragging,.ve-dragging *{cursor:grabbing!important;user-select:none!important}',
        '.ve-previewing .ve-drag-handle{display:none!important}',
        'html:not(.ve-previewing) [data-ve-org-media="cover"].ve-selected{outline:3px solid #1f7a3d!important;outline-offset:-3px!important}',
        'html:not(.ve-previewing) [data-ve-org-media="cover"].ve-hover{outline-offset:-3px!important}',
        '[contenteditable="true"],[contenteditable="plaintext-only"]{cursor:text;caret-color:#1f7a3d}',
        '[data-ve-field]:focus,[data-ve-section]:focus{outline:2px solid #1f7a3d!important;outline-offset:4px!important}',
        '.ve-empty::before{content:attr(data-ve-ph);opacity:.55;font-style:italic;pointer-events:none}',
        '.hero-title-line.ve-empty:not(:focus){font:500 12px/1.4 system-ui,sans-serif;text-transform:none;letter-spacing:0;min-height:0;width:fit-content;max-width:100%;padding:4px 8px;margin:6px 0;border:1px dashed currentColor;border-radius:5px}',
        '[data-ve-field="home-text"][data-ve-multiline]{white-space:pre-line}',
        '[data-ve-field][data-ve-multiline]:not([data-ve-overview]):not([data-ve-rich]){white-space:pre-wrap}',
        '[data-ve-item],[data-ve-pick],[data-ve-post],[data-ve-goto]{cursor:pointer}',
        '[data-ve-field]{cursor:text}',
        '.ve-hidden-card{opacity:.55}',
        '.ve-hidden-badge{display:inline-block;background:#b45309;color:#fff;font:700 10px/1 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:4px 8px;border-radius:999px;margin-bottom:8px}',
        '.ve-previewing .ve-add-tile,.ve-previewing .ve-hero-hint,.ve-previewing .ve-hidden-badge{display:none!important}',
        '.ve-previewing [data-ve-field].ve-empty,.ve-previewing .ve-ghost{display:none!important}',
        '.ve-previewing [data-ve-org-add],.ve-previewing [data-ve-org-details],.ve-previewing [data-ve-org-categories],.ve-previewing [data-ve-org-gallery-add],.ve-previewing .org-cover-editor{display:none!important}',
        '.ve-previewing [data-ve-org-assign-category],.ve-previewing [data-ve-enrollment-add]{display:none!important}',
        '.ve-previewing .ve-hover,.ve-previewing .ve-selected{outline:none!important}',
        '.ve-previewing [data-ve-field],.ve-previewing [data-ve-item],.ve-previewing [data-ve-pick],.ve-previewing [data-ve-post],.ve-previewing [data-ve-goto],.ve-previewing [data-ve-img]{cursor:default!important}',
        '[data-ve-img],[data-ve-person],[data-ve-office],[data-ve-slide],[data-ve-icon]{cursor:pointer}',
        '[data-ve-img]{-webkit-user-drag:none;user-select:none}',
        '.ve-ghost{opacity:1!important;background:#fff!important;color:#0b4228!important;border:2px dashed #176b42!important;box-shadow:0 2px 10px rgba(0,0,0,.18)!important}',
        '.ve-ghost .ve-empty::before{opacity:1;font-style:normal;color:inherit}',
        '.ve-ghost:hover,.ve-ghost:focus-within{background:#fff7df!important;border-color:#0b4228!important;box-shadow:0 0 0 3px rgba(234,186,59,.45)!important}',
        '.ve-ghost svg{display:none}',
        '[data-ve-status="draft"]::after{content:"DRAFT \\00B7  hidden from visitors";position:absolute;top:92px;right:20px;z-index:30;background:#b45309;color:#fff;font:700 11px/1 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;padding:8px 12px;border-radius:99px;pointer-events:none}',
        '.ve-locked{opacity:.85;cursor:not-allowed}',
        '.ve-add-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:220px;border:2px dashed #1f7a3d;border-radius:14px;color:#1f7a3d;background:rgba(31,122,61,.05);font:600 15px system-ui,sans-serif;cursor:pointer;text-align:center;padding:1rem}',
        '.ve-add-tile:hover{background:rgba(31,122,61,.12)}',
        '.template-badges>.ve-add-tile[data-ve-type="badge"]{flex-direction:row;align-self:stretch;flex:0 0 auto;min-height:0;padding:0 .6rem;gap:.6rem;border-radius:10px;font-size:.85rem}',
        '.ve-extra{max-width:900px;margin:0 auto;padding:2rem 1.25rem 4rem}',
        '.ve-extra>h3{font:700 13px system-ui,sans-serif;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0 0 1rem}',
        '.ve-flash{animation:veFlash 1.2s ease}@keyframes veFlash{0%,60%{box-shadow:0 0 0 6px rgba(211,166,58,.55)}100%{box-shadow:0 0 0 0 rgba(211,166,58,0)}}'
    ].join('\n');

    function injectStyle(doc) {
        doc.documentElement.classList.add('gnc-editor-preview');
        var st = doc.createElement('style'); st.id = 've-style'; st.textContent = FRAME_CSS;
        (doc.head || doc.documentElement).appendChild(st);
        try { doc.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
        try { doc.execCommand('styleWithCSS', false, 'false'); } catch (e) {}
    }

    function withParam(url, key, val) {
        var u = new URL(url, window.location.origin);
        u.searchParams.set(key, val);
        return u.pathname + u.search + u.hash;
    }
    /** Load (or reload) the page. keepScroll puts the reader back where they were. */
    function loadFrame(url, keepScroll) {
        if (S.publishing) { S.publishReload = {url:url, keepScroll:keepScroll}; return; }
        discardImagePositions();
        closePop(); select(null);
        if (url) S.url = url;
        S.scrollKeep = (keepScroll && S.win) ? (S.win.scrollY || 0) : null;
        S.fallback = false;
        loader.hidden = false;
        S.frame.removeAttribute('srcdoc');
        S.frame.src = withParam(S.url, '_ve', Date.now());
    }
    function loadSrcdoc(html) {
        S.fallback = true; loader.hidden = false;
        S.frame.srcdoc = html;
    }

    function fatal(msg) {
        loader.hidden = false;
        loader.innerHTML = '';
        loader.appendChild(h('div', { class: 've-fatal' }, [icon('exclamation-triangle'), h('strong', { text: 'The preview could not open.' }), h('span', { text: msg }),
            h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Try again', on: { click: function () { window.location.reload(); } } })]));
    }

    S.frame.addEventListener('load', function () {
        var doc = null;
        try { doc = S.frame.contentDocument; } catch (e) { doc = null; }
        if (!doc || !doc.body || !doc.location || doc.location.href === 'about:blank' && !S.fallback) return;
        if (!S.fallback && /\/(login|auth)\b/i.test(doc.location.pathname)) {
            return fatal('Your session has ended. Reload this page and sign in again.');
        }
        if (editorFeedback) { editorFeedback.dispose(); editorFeedback=null; }
        if(textLayout){textLayout.dispose();textLayout=null;}
        S.doc = doc; S.win = S.frame.contentWindow; S.loadCount++;
        if (S.statusControlObserver) S.statusControlObserver.disconnect();
        S.statusControlObserver = new MutationObserver(function (records) {
            if (records.some(function (record) {
                return (record.target.matches && record.target.matches('.image-frame-trigger')) || Array.from(record.addedNodes).concat(Array.from(record.removedNodes)).some(function (node) { return node.matches && node.matches('.image-frame-trigger'); });
            })) positionStatus();
        });
        S.statusControlObserver.observe(doc.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'style'] });
        if (S.statusObserver) S.statusObserver.disconnect();
        if (window.ResizeObserver) {
            S.statusObserver = new ResizeObserver(positionStatus);
            S.statusObserver.observe(S.frame);
            var statusNavbar = doc.getElementById('mainNavbar');
            if (statusNavbar) S.statusObserver.observe(statusNavbar);
        }
        S.win.addEventListener('scroll', positionStatus, { passive: true });
        if (window.GNCImageFraming) window.GNCImageFraming.watch(doc);
        injectStyle(doc); bindDoc(doc);
        var again = S.adapter.mount(doc);
        if (again) return;                       // adapter swapped in its own canvas; wait for that load
        if(C.canPublish && S.win.GNCTextLayout)textLayout=S.win.GNCTextLayout.create({state:function(){return {busy:S.busy,preview:S.preview};},stage:stageTextPlacement});
        if (window.GNCEditorFeedback) editorFeedback = window.GNCEditorFeedback.create({
            document:doc,uiDocument:D,frame:S.frame,
            state:function(){return {pending:S.adapter.dirtyCount(),busy:S.busy,preview:S.preview};},
            serializeText:function(el){return el.__ve && el.__ve.field ? readField(el) : cleanHtml(el.innerHTML);},
            forcedTargets:function(){return S.adapter.feedbackTargets ? S.adapter.feedbackTargets() : [];}
        });
        loader.hidden = true;
        if (S.preview) applyPreview();
        if (S.scrollKeep !== null) { S.win.scrollTo(0, S.scrollKeep); S.scrollKeep = null; }
        chrome();
        if (S.adapter.afterMount) S.adapter.afterMount();
        if (drafts && drafts.supported()) {
            loader.hidden = false; S.busy = true;
            drafts.load(S.url).finally(function () { S.busy = false; loader.hidden = true; chrome(); });
        }
    });

    // ══════════════════ fields: plain-text editing in place ══════════════════
    function fieldOf(t) { return t && t.closest ? t.closest('[data-ve-field]') : null; }
    function sectionElOf(t) { return t && t.closest ? t.closest('[data-ve-section]') : null; }

    function blocksText(el) {
        var out = [];
        Array.prototype.forEach.call(el.childNodes, function (n) {
            var t = n.nodeType === 3 ? n.nodeValue : (n.nodeType === 1 ? (n.innerText || n.textContent) : '');
            t = String(t || '').replace(/\u00a0/g, ' ').replace(/\r/g, '').trim();
            if (t) out.push(t);
        });
        return out.join('\n\n');
    }
    function readField(el) {
        var v = el.__ve || {};
        if (v.rich) return richStorage(el.innerHTML);
        if (v.overview) return cleanHtml(el.innerHTML);
        if (v.blocks) return blocksText(el);
        var t = String(el.innerText != null ? el.innerText : el.textContent).replace(/\u00a0/g, ' ').replace(/\r/g, '');
        t = v.multi ? t.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim() : t.replace(/\s+/g, ' ').trim();
        return t;
    }
    function markEmpty(el) { el.classList.toggle('ve-empty', readField(el) === ''); }
    /** Make an element on the page a plain-text field. onChange(el, text) runs on every edit. */
    function makeField(el, onChange, locked) {
        var ro = !!locked;
        el.setAttribute('contenteditable', ro ? 'false' : (el.hasAttribute('data-ve-blocks') || el.hasAttribute('data-ve-overview') || el.hasAttribute('data-ve-rich') ? 'true' : PLAIN));
        el.setAttribute('spellcheck', 'true');
        el.__ve = {
            field: true, max: parseInt(el.getAttribute('data-ve-max') || '0', 10) || 0,
            multi: el.hasAttribute('data-ve-multiline'), blocks: el.hasAttribute('data-ve-blocks'),
            overview: el.hasAttribute('data-ve-overview'),
            rich: el.hasAttribute('data-ve-rich'),
            label: el.getAttribute('data-ve-label') || '', onChange: onChange
        };
        markEmpty(el);
    }
    /** Keep pasted/new paragraphs looking like the page's own paragraphs. */
    function normalizeBlocks(el) {
        var first = el.querySelector('p[class]'); var cls = first ? first.className : '';
        Array.prototype.forEach.call(el.children, function (c) {
            if ((c.tagName === 'DIV' || c.tagName === 'P') && cls && !c.className) c.className = cls;
        });
    }

    // ══════════════════ events inside the page ══════════════════
    var HOVER_SEL = '[data-ve-team],[data-ve-team-image],[data-ve-sport],[data-ve-sport-image],[data-ve-org-icon],[data-ve-org-card],[data-ve-org-media],[data-ve-org-page-media],[data-ve-org-categories],[data-ve-org-gallery],[data-ve-org-gallery-add],[data-ve-org-add],[data-ve-field],[data-ve-section],[data-ve-img],[data-ve-basic-image],[data-ve-program-image],[data-ve-program-card],[data-ve-basic-logo-id],[data-ve-person],[data-ve-office],[data-ve-add],[data-ve-icon],[data-ve-item],[data-ve-pick],[data-ve-post],[data-ve-goto]';
    function bindDoc(doc) {
        var win = doc.defaultView;
        doc.addEventListener('click', function (e) {
            if(e.target.closest('[data-text-layout-control]') || (textLayout && textLayout.consume())){e.preventDefault();return;}
            if(S.adapter.consumeClick && S.adapter.consumeClick()){e.preventDefault();e.stopImmediatePropagation();return;}
            var t = e.target;
            if (S.preview) {                      // Preview: nothing is editable; links still move between pages
                var pa = t.closest ? t.closest('a') : null;
                if (pa) { e.preventDefault(); if (S.adapter.onLink) S.adapter.onLink(pa); }
                return;
            }
            var d = S.adapter.pick(t, e);
            if (!d && window.GNCImageFraming && window.GNCImageFraming.findTarget(t) === t) d = {kind:'framed-image',el:t};
            var a = t.closest ? t.closest('a') : null;
            if (d && d.kind === 'none') { e.preventDefault(); return; }       // the item already opened its own popover
            if (d) {
                if (d.stop) e.stopPropagation();
                if (d.prevent !== false && !(d.el && d.el.isContentEditable && !d.noCaret)) e.preventDefault();
                else if (a) e.preventDefault();
                select(d); return;
            }
            if (a) {
                e.preventDefault();
                var nav = S.adapter.onLink ? S.adapter.onLink(a) : false;
                Promise.resolve(nav).then(function (handled) {
                    if (!handled) toast('Links are switched off while you edit. Use the page list at the top to move between pages.', 'ok');
                });
                return;
            }
            if (pop.hidden === false) closePop();
            select(null);
        }, true);
        doc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
        doc.addEventListener('dragstart', function (e) { e.preventDefault(); }, true);
        doc.addEventListener('mouseover', function (e) {
            if (S.preview) return;
            var el = e.target.closest ? e.target.closest(HOVER_SEL) : null;
            if (!el && e.target.closest && S.adapter.hoverFallback) el = S.adapter.hoverFallback(e.target);
            if (S.hoverEl && S.hoverEl !== el) S.hoverEl.classList.remove('ve-hover');
            S.hoverEl = el; if (el && el !== (S.sel && S.sel.el)) el.classList.add('ve-hover');
        });
        doc.addEventListener('mouseleave', function () { if (S.hoverEl) { S.hoverEl.classList.remove('ve-hover'); S.hoverEl = null; } });
        doc.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); shortcutSave(); return; }
            var emptyMedia = e.target.closest && e.target.closest('.be-empty-media,[data-ve-icon="academic-feature"],[data-ve-icon="department-statement"],[data-ve-icon="program-statement"],[data-ve-icon="tuition"],[data-ve-org-icon]');
            if (emptyMedia && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); emptyMedia.click(); return; }
            var el = fieldOf(e.target);
            if (e.key === 'Escape') { if (D.activeElement === S.frame && doc.activeElement) doc.activeElement.blur(); select(null); return; }
            if (el && el.__ve && e.key === 'Enter' && !el.__ve.multi) { e.preventDefault(); el.blur(); }
        });
        doc.addEventListener('beforeinput', function (e) {
            var el = fieldOf(e.target); if (!el || !el.__ve || !el.__ve.max) return;
            if (String(e.inputType || '').indexOf('insert') !== 0 || e.inputType === 'insertFromPaste') return;
            var s = win.getSelection(); var selLen = s && !s.isCollapsed ? s.toString().length : 0;
            if (readField(el).length - selLen >= el.__ve.max) e.preventDefault();
        });
        doc.addEventListener('paste', function (e) {
            var el = fieldOf(e.target), sec = sectionElOf(e.target);
            var cd = e.clipboardData || win.clipboardData; if (!cd) return;
            if (el && el.__ve && el.isContentEditable) {
                e.preventDefault();
                if (el.__ve.overview || el.__ve.rich) {
                    var rich = cd.getData('text/html');
                    var pasted = rich ? cleanHtml(rich) : plainToHtml(cd.getData('text/plain') || '');
                    if (el.__ve.max && readField(el).length + pasted.length > el.__ve.max) {
                        toast('That content is too long. Paste a smaller section.', 'error'); return;
                    }
                    doc.execCommand('insertHTML', false, pasted);
                    return;
                }
                var text = cd.getData('text/plain') || '';
                if (!el.__ve.multi) text = text.replace(/\s+/g, ' ');
                if (el.__ve.max) {
                    var s = win.getSelection(); var selLen = s && !s.isCollapsed ? s.toString().length : 0;
                    text = text.slice(0, Math.max(0, el.__ve.max - (readField(el).length - selLen)));
                }
                doc.execCommand('insertText', false, text);
            } else if (sec && sec.isContentEditable) {
                e.preventDefault();
                var html = cd.getData('text/html');
                doc.execCommand('insertHTML', false, html ? cleanHtml(html) : plainToHtml(cd.getData('text/plain') || ''));
            }
        });
        doc.addEventListener('input', function (e) {
            var el = fieldOf(e.target);
            if (el && el.__ve) {
                if (el.__ve.blocks) normalizeBlocks(el);
                markEmpty(el); el.__ve.onChange(el, readField(el));
            } else {
                var sec = sectionElOf(e.target);
                if (sec && sec.__ve && sec.__ve.onChange) { sec.__ve.onChange(sec); sec.classList.toggle('ve-empty', !(sec.textContent || '').trim()); }
            }
            renderToolbar(); chrome();
        });
        var raf = 0;
        doc.addEventListener('selectionchange', function () {
            if (!S.sel || (S.sel.kind !== 'rich' && !(S.sel.el.__ve && (S.sel.el.__ve.overview || S.sel.el.__ve.rich))) || raf) return;
            raf = win.requestAnimationFrame(function () { raf = 0; renderToolbar(); });
        });
        win.addEventListener('beforeunload', function () { /* the frame itself is reloading; nothing to keep */ });
    }

    function select(d) {
        if (S.sel && d && S.sel.el === d.el && S.sel.kind === d.kind) { S.sel = d; renderToolbar(); return; }
        if (S.sel && S.sel.el && S.sel.el.classList) S.sel.el.classList.remove('ve-selected');
        S.sel = d || null;
        if (S.hoverEl) S.hoverEl.classList.remove('ve-hover');
        if (d && d.el) { d.el.classList.add('ve-selected'); d.el.classList.remove('ve-hover'); }
        if(d&&d.kind==='sports-background'){
            [D,S.doc].forEach(function(doc){var trigger=doc&&doc.querySelector('.image-frame-trigger');if(trigger)trigger.hidden=true;});
        }
        closePop(); renderToolbar();
    }

    // ══════════════════ the floating toolbar ══════════════════
    function buildItem(it) {
        if (it.type === 'sep') return h('span', { class: 've-sep', role: 'separator' });
        if (it.type === 'chip') return h('span', { class: 've-chip' + (it.tone ? ' ve-chip-' + it.tone : ''), title: it.title || null }, [it.icon ? icon(it.icon) : null, it.text]);
        if (it.type === 'select') {
            var sel = h('select', { class: 've-sel', title: it.title || null, 'aria-label': it.title || 'Choose' },
                it.options.map(function (o) { return h('option', { value: o[0], text: o[1], selected: o[0] === it.value }); }));
            sel.addEventListener('change', function () { it.onChange(sel.value); });
            return sel;
        }
        return h('button', {
            type: 'button', class: 've-b' + (it.on ? ' is-on' : '') + (it.label ? ' has-label' : '') + (it.danger ? ' is-danger' : '') + (it.primary ? ' is-primary' : ''),
            title: it.title || it.label || '', 'aria-label': it.title || it.label || '', 'aria-pressed': it.on === undefined ? null : (it.on ? 'true' : 'false'), disabled: it.disabled,
            on: { click: function (e) { e.preventDefault(); it.run(e); } }
        }, [it.icon ? icon(it.icon) : null, it.label ? h('span', { text: it.label }) : null]);
    }
    function renderToolbar() {
        tb.innerHTML = '';
        var items = S.sel && S.adapter.toolbar ? S.adapter.toolbar(S.sel) : [];
        if(textLayout){
            var textTarget=S.sel && S.sel.el;
            var layoutMedia=textTarget && textTarget.hasAttribute('data-gnc-layout-media');
            textLayout.select(textTarget);
            if(textLayout.eligible(textTarget))items=[{icon:'arrows-move',label:layoutMedia?'Move and resize logo':'Move and resize text',on:textLayout.active(),run:function(){textLayout.toggle();renderToolbar();}},{icon:'grid-3x3',label:'Snap to grid',title:'Snap to grid',on:textLayout.snapping(),run:function(){textLayout.toggleSnap();renderToolbar();}},{icon:'arrow-counterclockwise',label:layoutMedia?'Reset logo placement':'Reset text placement',run:function(){textLayout.reset();}}].concat(items||[]);
            if(textLayout.eligible(textTarget)&&!layoutMedia)items=[
                {icon:'dash',label:'Smaller text',run:function(){textLayout.changeFontSize(-2);renderToolbar();}},
                {type:'chip',text:Math.round(textLayout.fontSize()*100)/100+' px'},
                {icon:'plus',label:'Bigger text',run:function(){textLayout.changeFontSize(2);renderToolbar();}},
                {icon:'arrow-counterclockwise',label:'Default text size',run:function(){textLayout.changeFontSize(null);renderToolbar();}}
            ].concat(items);
        }
        if (S.sel && window.GNCImageFraming) {
            var photoTarget = (S.sel.kind === 'office' || S.sel.kind === 'person')
                ? S.sel.el.querySelector('[data-ve-img="office-photo"],[data-ve-img="person-photo"]') : null;
            var frameTarget = window.GNCImageFraming.findTarget(photoTarget || S.sel.el);
            if (frameTarget) items = [{icon:'arrows-move',label:'Reposition image',run:function () { window.GNCImageFraming.edit(frameTarget); }}].concat(items || []);
        }
        if(S.sel&&S.sel.el&&S.sel.el.__ve&&S.sel.el.__ve.rich&&!items.some(function(item){return item.type==='select'&&item.title==='Text style';}))items=richTextTools().concat(items||[]);
        if (!items || !items.length) { tb.hidden = true; return; }
        items.forEach(function (it) { if (it) tb.appendChild(buildItem(it)); });
        tb.hidden = false; tb.style.visibility = 'hidden'; placeTb(true);
    }
    var lastPos = '';
    function placeTb(force) {
        var s = S.sel;
        if (!s || !s.el || !s.el.isConnected || tb.hidden) return;
        var fr = S.frame.getBoundingClientRect(), r = s.el.getBoundingClientRect();
        var tw = tb.offsetWidth, th = tb.offsetHeight, top, left;
        var offTop = r.bottom < 0, offBottom = r.top > fr.height;
        if (s.anchor === 'bottom') {
            var vis = Math.min(r.bottom, fr.height);
            top = fr.top + vis - th - 84;
            left = fr.left + (fr.width - tw) / 2;
        } else {
            if (offTop || offBottom) { tb.style.visibility = 'hidden'; return; }
            if(s.anchor==='image'){
                var navbar=S.doc.querySelector('#mainNavbar'),navBottom=navbar?navbar.getBoundingClientRect().bottom:0;
                var imageTop=Math.max(0,r.top,navBottom),imageBottom=Math.min(r.bottom,fr.height);
                if(imageBottom-imageTop<th+24){tb.style.visibility='hidden';return;}
                top=fr.top+imageBottom-th-16;
                left=fr.left+r.left+(r.width-tw)/2;
            } else {
            top = fr.top + r.top - th - 10;
            if (top < fr.top + 6) top = Math.min(fr.top + Math.max(r.bottom, 0) + 10, fr.bottom - th - 8);
            if (r.height > fr.height - th - 40 && r.top < th + 16) top = fr.top + 10;
            left = fr.left + r.left;
            }
        }
        left = Math.max(fr.left + 6, Math.min(left, fr.right - tw - 6));
        top = Math.max(fr.top + 6, top);
        var key = Math.round(top) + ',' + Math.round(left) + ',' + tw;
        if (force || key !== lastPos) { tb.style.top = Math.round(top) + 'px'; tb.style.left = Math.round(left) + 'px'; lastPos = key; }
        tb.style.visibility = 'visible';
    }
    (function tick() { if (S.sel) placeTb(); window.requestAnimationFrame(tick); })();
    tb.addEventListener('mousedown', function (e) { if (!e.target.closest('select,input,textarea')) e.preventDefault(); });

    /** Run a formatting command in the page (works on the page's current selection). */
    function exec(cmd, val) {
        try { S.win.focus(); S.doc.execCommand(cmd, false, val === undefined ? null : val); } catch (e) {}
        var el = S.sel && S.sel.el;
        if (el && el.__ve && el.__ve.onChange) el.__ve.onChange(el, el.__ve.field ? readField(el) : undefined);
        renderToolbar(); chrome();
    }

    // ══════════════════ top bar: status, Save draft / Publish, devices ══════════════════
    function blockType() {
        var v = ''; try { v = String(S.doc.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) {}
        return { h2: 'h2', h3: 'h3', h4: 'h3', blockquote: 'blockquote' }[v] || 'p';
    }
    function state(cmd) { try { return S.doc.queryCommandState(cmd); } catch (e) { return false; } }
    function linkAction() {
        var sel = S.win.getSelection(); if (!sel || !sel.rangeCount) return;
        var range = sel.getRangeAt(0).cloneRange();
        var node = range.commonAncestorContainer; var a = node.nodeType === 1 ? node : node.parentNode; a = a && a.closest ? a.closest('a') : null;
        askText({
            label: 'Link address', value: a ? a.getAttribute('href') : '', placeholder: 'https://…   or   /pages/…', max: 500,
            hint: 'Select the words first. Leave it empty and press Apply to remove a link.',
            done: function (v) {
                S.win.focus(); sel.removeAllRanges(); sel.addRange(range);
                if (!v) { exec('unlink'); return; }
                var href = safeHref(v);
                if (!href) { toast('That link isn’t allowed. Use https://, mailto:, tel: or a /path.', 'error'); return; }
                if (range.collapsed && !a) exec('insertHTML', '<a href="' + esc(href) + '">' + esc(href) + '</a>'); else exec('createLink', href);
            }
        });
    }

    function richTextTools() {
        var it = [];
        it.push({ type: 'select', title: 'Text style', value: blockType(), options: [['p', 'Paragraph'], ['h2', 'Heading'], ['h3', 'Subheading'], ['blockquote', 'Quote']], onChange: function (v) { exec('formatBlock', '<' + v + '>'); } });
        it.push({ type: 'sep' });
        it.push({ icon: 'type-bold', title: 'Bold (Ctrl+B)', on: state('bold'), run: function () { exec('bold'); } });
        it.push({ icon: 'type-italic', title: 'Italic (Ctrl+I)', on: state('italic'), run: function () { exec('italic'); } });
        it.push({ icon: 'type-underline', title: 'Underline (Ctrl+U)', on: state('underline'), run: function () { exec('underline'); } });
        it.push({ type: 'sep' });
        it.push({ icon: 'list-ul', title: 'Bulleted list', on: state('insertUnorderedList'), run: function () { exec('insertUnorderedList'); } });
        it.push({ icon: 'list-ol', title: 'Numbered list', on: state('insertOrderedList'), run: function () { exec('insertOrderedList'); } });
        it.push({ type: 'sep' });
        it.push({ icon: 'text-left', title: 'Align left', on: state('justifyLeft'), run: function () { exec('justifyLeft'); } });
        it.push({ icon: 'text-center', title: 'Align centre', on: state('justifyCenter'), run: function () { exec('justifyCenter'); } });
        it.push({ icon: 'text-right', title: 'Align right', on: state('justifyRight'), run: function () { exec('justifyRight'); } });
        it.push({ icon: 'justify', label: 'Justify', title: 'Align text to both margins', on: state('justifyFull'), run: function () { exec('justifyFull'); } });
        it.push({ type: 'sep' });
        it.push({ icon: 'link-45deg', title: 'Add or change a link', on: state('createLink'), run: linkAction });
        it.push({ icon: 'eraser', title: 'Clear formatting', run: function () { exec('removeFormat'); } });
        it.push({ type: 'sep' });
        return it;
    }

    function positionStatus() {
        if (!statusEl || !S.doc) return;
        var navbar = S.doc.getElementById('mainNavbar');
        var canvas = $('#veCanvas');
        if (!canvas) return;
        var bottom = navbar ? navbar.getBoundingClientRect().bottom : 0;
        statusEl.style.top = Math.max(16, S.frame.getBoundingClientRect().top - canvas.getBoundingClientRect().top + bottom + 12) + 'px';
        var reposition = S.doc.querySelector('.image-frame-trigger:not([hidden])');
        if (reposition) {
            var frameBounds = S.frame.getBoundingClientRect(), control = reposition.getBoundingClientRect(), notice = statusEl.getBoundingClientRect();
            var controlTop = frameBounds.top + control.top, controlBottom = frameBounds.top + control.bottom;
            var controlLeft = frameBounds.left + control.left, controlRight = frameBounds.left + control.right;
            if (notice.left < controlRight && notice.right > controlLeft && notice.top < controlBottom && notice.bottom > controlTop) {
                statusEl.style.top = controlBottom - canvas.getBoundingClientRect().top + 12 + 'px';
            }
        }
    }
    function setStatus(text, tone) {
        statusEl.className = 've-status ve-status-' + (tone || 'ok');
        statusEl.textContent = '';
        statusEl.appendChild(icon(tone === 'warn' ? 'circle-fill' : tone === 'busy' ? 'arrow-repeat' : tone === 'err' ? 'exclamation-circle-fill' : 'check-circle-fill'));
        statusEl.appendChild(D.createTextNode(' ' + text));
        positionStatus();
    }
    function paintBtn(btn, def) {
        if (!def) { btn.hidden = true; return; }
        btn.hidden = false; btn.disabled = !!def.disabled || S.busy;
        btn.title = def.title || '';
        btn.innerHTML = ''; btn.appendChild(icon(def.icon)); btn.appendChild(h('span', { text: def.label }));
    }
    function chrome() {
        if (!S.adapter) return;
        var a = S.adapter.actions();
        paintBtn(btnSecondary, a.secondary); paintBtn(btnPrimary, a.primary);
        if(C.requiresApproval && a.primary && !btnPrimary.hidden) { btnPrimary.textContent='Submit for approval';btnPrimary.title='Send proposed changes to Super Admin'; }
        else if(C.reviewSubmission && a.primary && !btnPrimary.hidden) btnPrimary.textContent='Approve and publish';
        var saveDraft = $('#veSaveDraft'), discardDraft = $('#veDiscardDraft');
        if (drafts && drafts.supported()) {
            if (a.secondary && a.secondary.label === 'Save draft') btnSecondary.hidden = true;
            saveDraft.hidden = false; saveDraft.disabled = S.busy || !drafts.available() || !S.adapter.dirtyCount() || drafts.saved();
            discardDraft.hidden = !drafts.hasDraft(); discardDraft.disabled = S.busy;
        } else { if (saveDraft) saveDraft.hidden = true; if (discardDraft) discardDraft.hidden = true; }
        if (!S.busy) { var st = S.adapter.status(); setStatus(drafts && drafts.hasDraft() && drafts.saved() ? 'Draft saved · not live yet' : st.text, st.tone); }
        var o = $('#veOutlineBtn'); if (o) o.hidden = !S.adapter.outline;
        if (btnCancel) btnCancel.hidden = !unsavedPageChanges() || S.preview;
        if (btnPageOpts) btnPageOpts.hidden = !(S.adapter.hasPageOptions && S.adapter.hasPageOptions()) || S.preview;
        if (editorFeedback) editorFeedback.refresh();
        if(textLayout)textLayout.refresh();
    }
    /** Run an async save with the buttons locked and a status message. */
    function run(label, fn) {
        if (S.busy) return Promise.resolve();
        S.publishing = /^Publishing/.test(label);
        S.busy = true; setStatus(label, 'busy'); chrome();
        return Promise.resolve().then(function () {
            if (/^Publishing/.test(label)) return publishTextPlacements().then(function(){if(imagePositionCount())return publishImagePositions();});
        }).then(fn).catch(function (e) {
            console.error(e); toast(e.message || 'Something went wrong. Please try again.', 'error');
        }).then(async function () {
            if(C.reviewSubmission && !C.requiresApproval && /^Publishing/.test(label) && !S.adapter.dirtyCount()) {
                var approved=await send('/admin/action/editor-draft-handler.php',{action:'complete_review',page:C.page.slug,route:new URL(S.url,location.origin).pathname+new URL(S.url,location.origin).search,review_submission:C.reviewSubmission});
                if(!approved.ok)throw Error(approved.error);C.reviewSubmission=0;
            }
            if ((/^Publishing/.test(label) || /^Sending/.test(label)) && drafts && drafts.hasDraft() && !S.adapter.dirtyCount()) await drafts.clear();
            else if (/^Publishing/.test(label) && drafts && drafts.hasDraft() && S.adapter.dirtyCount()) await drafts.save();
            S.publishing = false;
            S.allowIncomplete = false;
            S.busy = false; chrome();
            if (S.publishReload) { var pendingReload=S.publishReload;S.publishReload=null;if(!S.adapter.dirtyCount())loadFrame(pendingReload.url,pendingReload.keepScroll); }
        }).catch(function (e) { S.publishing = false; S.allowIncomplete = false; S.publishReload=null; S.busy = false; toast(e.message, 'error'); chrome(); });
    }
    function shortcutSave() {
        var draftButton = $('#veSaveDraft');
        if (draftButton && !draftButton.hidden) { if (!draftButton.disabled) draftButton.click(); return; }
        var a = S.adapter.actions(); var b = a.secondary && !a.secondary.disabled ? btnSecondary : btnPrimary;
        if (b && !b.disabled && !b.hidden) b.click();
    }
    D.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); shortcutSave(); } });
    btnSecondary.addEventListener('click', function () { if (S.adapter.runSecondary) S.adapter.runSecondary(); });
    btnPrimary.addEventListener('click', function () {
        if(C.requiresApproval && drafts && drafts.supported()) { confirmPublish('',function(){});return; }
        if (S.adapter.runPrimary) S.adapter.runPrimary();
    });

    $$('.ve-devices [data-device]').forEach(function (b) {
        b.addEventListener('click', function () {
            S.device = b.getAttribute('data-device');
            S.stage.setAttribute('data-device', S.device);
            $$('.ve-devices [data-device]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); x.classList.toggle('active', x === b); });
            if (S.adapter.onDevice) window.setTimeout(S.adapter.onDevice, 350);
        });
    });

    // leaving the editor (page list, back link, closing the tab) with unsaved work
    var leaving = false;
    window.addEventListener('beforeunload', function (e) {
        if (!leaving && unsavedPageChanges()) { e.preventDefault(); e.returnValue = ''; }
    });
    D.addEventListener('click', async function (e) {
        var a = e.target.closest ? e.target.closest('a[data-ve-nav]') : null;
        if (!a) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        if (unsavedPageChanges() && !await window.AdminDialogs.confirm('You have unsaved changes on this page.\n\nLeave without saving them?')) { e.preventDefault(); return; }
        leaving = true;
        window.__veLeaving = true;
        window.location.href = a.href;
    });
    async function guardDirty(msg) {
        return !unsavedPageChanges() || await window.AdminDialogs.confirm(msg || 'This reloads the preview, so your unsaved changes would be lost.\n\nContinue?');
    }

    // ══════════════════ Preview · Cancel · refresh after saving ══════════════════
    var btnPreview = $('#vePreviewBtn'), btnCancel = $('#veCancel'), btnPageOpts = $('#vePageOpts'), btnPosts = $('#vePostsBtn');
    S.preview = false;

    /** Preview = the page exactly as visitors see it: no outlines, nothing clickable for editing. */
    function applyPreview() {
        if (!S.doc) return;
        var on = !!S.preview;
        S.doc.documentElement.classList.toggle('ve-previewing', on);
        Array.prototype.forEach.call(S.doc.querySelectorAll('[contenteditable]'), function (el) {
            if (on) { if (el.__vePrev === undefined) { el.__vePrev = el.getAttribute('contenteditable'); el.setAttribute('contenteditable', 'false'); } }
            else if (el.__vePrev !== undefined) { el.setAttribute('contenteditable', el.__vePrev); delete el.__vePrev; }
        });
    }
    function setPreview(on) {
        S.preview = !!on;
        select(null); closePop(); applyPreview();
        D.body.classList.toggle('ve-previewing', S.preview);
        if (btnPreview) {
            btnPreview.classList.toggle('is-on', S.preview);
            btnPreview.setAttribute('aria-pressed', S.preview ? 'true' : 'false');
            btnPreview.innerHTML = '';
            btnPreview.appendChild(icon(S.preview ? 'pencil-square' : 'eye'));
            btnPreview.appendChild(h('span', { text: S.preview ? 'Back to editing' : 'Preview' }));
            btnPreview.title = S.preview ? 'Go back to editing the page' : 'See the page as visitors see it (no editing outlines)';
        }
        if (S.preview) toast('Preview: this is how visitors see the page. Press “Back to editing” to continue.', 'ok');
    }
    if (btnPreview) btnPreview.addEventListener('click', function () { setPreview(!S.preview); });

    /** Show a message after the editor screen reloads (the screen reloads after a save, so its side panel shows fresh data). */
    function toastNextLoad(msg, type) { try { window.sessionStorage.setItem('veToast', JSON.stringify({ m: msg, t: type || 'ok' })); } catch (e) {} }
    (function () {
        try {
            var j = window.sessionStorage.getItem('veToast');
            if (!j) return;
            window.sessionStorage.removeItem('veToast');
            var o = JSON.parse(j);
            window.setTimeout(function () { toast(o.m, o.t); }, 500);
        } catch (e) {}
    })();

    /** Which editor page (and sub-page) a website address belongs to. */
    function entityRoute(url) {
        var u; try { u = new URL(url, window.location.origin); } catch (e) { return null; }
        var p = u.pathname, slug = u.searchParams.get('slug') || '', m;
        if (/\/program\.php$/.test(p)) return { page: 'programs', sub: slug };
        if (/\/college-department\.php$/.test(p)) return { page: slug === 'graduate-school' ? 'graduate-school' : 'college-departments', sub: slug };
        if (/\/college-dept\.php$/.test(p)) return { page: 'college-departments', sub: '' };
        if (/\/basic-edu-dept\.php$/.test(p)) return { page: 'basic-education', sub: '' };
        if (/\/student-org\.php$/.test(p)) return { page: 'student-organizations', sub: '' };
        if (/\/student-achievement\.php$/.test(p)) return { page: 'student-achievements', sub: '' };
        if (/\/athletics-sports\.php$/.test(p)) return { page: 'athletics-sports', sub: '' };
        if (/\/dynamic-org\.php$/.test(p)) return { page: 'student-organizations', sub: slug };
        if ((m = /\/basic-edu-department\/([a-z0-9-]+)\.php$/i.exec(p))) return { page: 'basic-education', sub: m[1] };
        return null;
    }
    /** Reload the editor screen, optionally on another page: ({page, sub, program}). */
    function reloadEditor(msg, route, preserveSettings) {
        if (/^Published\./.test(msg || '') && drafts && drafts.hasDraft()) {
            drafts.clear().then(function () { reloadEditor(msg, route, preserveSettings); }).catch(function (e) { toast('Published, but the saved draft could not be removed: ' + e.message, 'error'); });
            return;
        }
        // The drawer saves independently. Keep its unsaved form values alive
        // when Publish has saved the visual canvas, rather than navigating away.
        if (preserveSettings && D.querySelector('.dept-media-card[data-dirty="1"], .pgm-dirty:not([hidden]), .org-manager-dirty:not([hidden])')) {
            loadFrame(S.url, true);
            toast((msg || 'Published.') + ' More settings still has unsaved changes.', 'ok');
            return;
        }
        if (msg) toastNextLoad(msg, 'ok');
        leaving = true;
        window.__veLeaving = true;
        if (!route) { window.location.reload(); return; }
        var q = '?page=' + encodeURIComponent(route.page) + (route.sub ? '&sub=' + encodeURIComponent(route.sub) : '') + (route.program ? '&program_id=' + encodeURIComponent(route.program) : '');
        window.location.href = window.location.pathname + q;
    }
    /** Cancel: throw away what is not saved yet by loading everything fresh from the server. */
    if (btnCancel) btnCancel.addEventListener('click', async function () {
        var n = S.adapter ? S.adapter.dirtyCount() : 0;
        if (!n) return;
        if (!await window.AdminDialogs.confirm('Throw away ' + plural(n, 'unsaved change') + '?\n\nThe page goes back to what is saved now.')) return;
        reloadEditor('Unsaved changes were discarded.');
    });

    // ══════════════════ News, announcements and events: the existing editors, in the side panel ══════════════════
    function postModule(type) {
        var m = (C.embed && C.embed.modules) || [];
        for (var i = 0; i < m.length; i++) if (m[i].key === type) return m[i];
        return null;
    }
    var POST_NAME = { announcement: 'Announcement', news: 'News article', event: 'Event' };
    var POST_ICON = { announcement: 'megaphone', news: 'newspaper', event: 'calendar-event' };
    function postPick(t) {
        var el = t && t.closest ? t.closest('[data-ve-post]') : null;
        if (!el) return null;
        return { kind: 'post', el: el, prevent: true, post: {
            type: el.getAttribute('data-ve-post'), id: parseInt(el.getAttribute('data-ve-id') || '0', 10) || 0,
            date: el.getAttribute('data-ve-date') || '', label: el.getAttribute('data-ve-label') || '' } };
    }
    function openPost(p) {
        var mod = postModule(p.type);
        if (!mod) { toast('Your account cannot edit this kind of post.', 'error'); return; }
        var url = mod.list;
        if (p.type !== 'event' && p.id) url = mod.list + '&modal=edit&id=' + p.id;
        else if (p.type === 'event') { var m = /^(\d{4})-(\d{1,2})$/.exec(p.date || ''); if (m) url = mod.list + '&month=' + (+m[2]) + '&year=' + m[1]; }
        openEmbed(url, mod.label + (p.label ? ': ' + clip(p.label, 40) : ''));
    }
    function postToolbar(sel) {
        var p = sel.post, mod = postModule(p.type), it = [];
        it.push({ type: 'chip', icon: POST_ICON[p.type] || 'megaphone', text: (POST_NAME[p.type] || 'Post') + ': ' + clip(p.label, 34) });
        it.push({ type: 'sep' });
        it.push({ icon: 'pencil-square', label: p.type === 'event' ? 'Open calendar' : 'Edit', primary: true, title: 'Edit this here, in the side panel', run: function () { openPost(p); } });
        if (mod && mod.create) it.push({ icon: 'plus-lg', label: 'New', title: 'Create another ' + (POST_NAME[p.type] || 'post').toLowerCase(), run: function () { openEmbed(mod.create, 'New ' + (POST_NAME[p.type] || 'post').toLowerCase()); } });
        if (mod) it.push({ icon: 'list-ul', label: 'All', title: 'See all ' + mod.label.toLowerCase(), run: function () { openEmbed(mod.list, mod.label); } });
        return it;
    }
    /** A small menu: pick which list to open (or create something new). */
    function postsMenu(x, y) {
        var mods = (C.embed && C.embed.modules) || [];
        if (!mods.length) { toast('Your account cannot edit news, announcements or events.', 'error'); return; }
        var rows = mods.map(function (m) {
            return h('div', { class: 've-pop-row' }, [
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', on: { click: function () { closePop(); openEmbed(m.list, m.label); } } }, [icon(String(m.icon).replace(/^bi-/, '')), ' ' + m.label]),
                m.create ? h('button', { type: 'button', class: 'btn btn-sm btn-gnc', on: { click: function () { closePop(); openEmbed(m.create, 'New ' + m.label.toLowerCase()); } } }, [icon('plus-lg'), ' New']) : null
            ]);
        });
        openPop(h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'News, announcements & events' }),
            h('small', { class: 've-pop-hint', text: 'They open right here. You can also click one on the page.' })].concat(rows)), x, y);
    }

    // ══════════════════ ADAPTER: text pages (sections with rich text) ══════════════════
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function cssEsc(s) { return String(s).replace(/["\\]/g, '\\$&'); }

    var FB_CSS = 'body{margin:0;font-family:Georgia,"Times New Roman",serif;color:#1f2937;background:#faf8f2;line-height:1.7}' +
        '.fb-hero{background:#0b3d22;color:#fff;padding:72px 8% 56px}.fb-hero h1{margin:6px 0 10px;font:800 2.6rem/1.1 system-ui,sans-serif}' +
        '.fb-hero small{font:700 12px system-ui,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#d3a63a}' +
        '.fb-hero p{margin:0;max-width:640px;opacity:.85;font:15px/1.5 system-ui,sans-serif}' +
        'main{max-width:860px;margin:0 auto;padding:36px 24px 80px}' +
        '.fb-sec{background:#fff;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,.07);padding:28px 32px;margin-bottom:24px}' +
        '.fb-sec h2{margin:0 0 12px;font:700 1.4rem system-ui,sans-serif;color:#0b3d22}' +
        '[data-ve-section]{min-height:3em;font-size:1.05rem}[data-ve-section] p{margin:0 0 1em}[data-ve-section] h2,[data-ve-section] h3{font-family:system-ui,sans-serif;color:#0b3d22}' +
        '[data-ve-section] blockquote{border-left:4px solid #d3a63a;margin:1em 0;padding:.2em 1em;color:#4b5563}[data-ve-section] a{color:#0b6b36}';

    function SectionsAdapter() {
        var page = C.page.slug;
        var photos = {};
        var enrollmentImages = {}, enrollmentChanges = {};
        var enrollmentCanvas = null, enrollmentBase = '', enrollmentRevision = '';
        function enrollmentCanvasDirty() { return enrollmentCanvas !== null && JSON.stringify(enrollmentCanvas) !== enrollmentBase; }
        function mediaChangeCount() { return Object.keys(photos).length + Object.keys(enrollmentChanges).length + (enrollmentCanvasDirty() ? 1 : 0); }
        function enrollmentCard(id) { return S.doc.querySelector('[data-ve-enrollment-step="'+cssEsc(id)+'"]'); }
        function paintEnrollmentCanvas(doc) {
            var grid = doc.querySelector('[data-ve-enrollment-grid]');
            if (!grid || !enrollmentCanvas) return;
            grid.textContent = '';
            enrollmentCanvas.forEach(function (step,index) {
                var key='basic-'+step.step_id;
                if (!enrollmentImages[key]) enrollmentImages[key]={label:'Basic Education - Step '+(index+1),url:step.illustration,default:step.illustration || '/assets/images/svg/enrollment-procedures/fresh-1.svg'};
                var image=enrollmentImages[key], change=enrollmentChanges[key];
                var card=h('div',{class:'enrollment-step-card is-visible','data-ve-enrollment-step':step.step_id});
                card.appendChild(h('div',{class:'enrollment-step-label',text:'STEP '+(index+1)}));
                function text(key,cls,label,max,multi) {
                    var el=h(key==='instruction'?'p':'div',{class:cls,'data-ve-field':'enrollment-step-text','data-ve-key':key,'data-ve-label':label,'data-ve-max':max,'data-ve-ph':label,text:step[key]});
                    if(multi){el.setAttribute('data-ve-multiline','');el.style.whiteSpace='pre-line';}
                    makeField(el,function(node,value){step[key]=value;});
                    return el;
                }
                card.appendChild(text('title','enrollment-step-title','Office / step title',150));
                card.appendChild(text('location','enrollment-step-sub','Location (optional)',150));
                card.appendChild(h('div',{class:'enrollment-step-icon','data-ve-img':'enrollment-illustration','data-ve-key':key,'data-ve-label':image.label},[
                    h('img',{src:change ? (change.preview || image.default) : (image.url || image.default),alt:step.title,width:100,height:100})
                ]));
                card.appendChild(text('instruction','enrollment-step-desc','Click to add instructions',2000,true));
                card.appendChild(h('div',{class:'enrollment-step-bar enrollment-step-bar--'+(index%2?'gold':'green')}));
                grid.appendChild(card);
            });
            var add=doc.querySelector('[data-ve-enrollment-add]');if(add)add.disabled=enrollmentCanvas.length>=40;
        }
        function addEnrollmentCard() {
            if (S.busy || !enrollmentCanvas || enrollmentCanvas.length>=40) return;
            var id=Array.from(crypto.getRandomValues(new Uint8Array(16)),function(b){return b.toString(16).padStart(2,'0');}).join('');
            enrollmentCanvas.push({step_id:id,title:'',location:'',instruction:'',illustration:'/assets/images/svg/enrollment-procedures/fresh-1.svg'});
            paintEnrollmentCanvas(S.doc); select(null); chrome();
            var el=enrollmentCard(id).querySelector('[data-ve-key="title"]');
            el.scrollIntoView({block:'center',behavior:'instant'});el.focus();
            select({kind:'enrollment-step-field',el:el,id:id,prevent:false});
        }
        function enrollmentStepTools(id,chip) {
            var index=enrollmentCanvas.findIndex(function(step){return step.step_id===id;});
            if(index<0)return [];
            function move(offset){if(S.busy)return;var step=enrollmentCanvas.splice(index,1)[0];enrollmentCanvas.splice(index+offset,0,step);paintEnrollmentCanvas(S.doc);select(null);chrome();}
            var tools=chip?[{type:'chip',text:'Step '+(index+1),icon:'pencil'}]:[];
            return tools.concat([
                {icon:'arrow-up',label:'Move step up',disabled:index===0,run:function(){move(-1);}},
                {icon:'arrow-down',label:'Move step down',disabled:index===enrollmentCanvas.length-1,run:function(){move(1);}},
                {icon:'trash',label:'Remove step',danger:true,run:function(){
                    confirmPop('Remove this step?','It will be removed from the website when you publish.','Remove step',function(){
                        var change=enrollmentChanges['basic-'+id];if(change&&change.preview)URL.revokeObjectURL(change.preview);
                        delete enrollmentChanges['basic-'+id];enrollmentCanvas.splice(index,1);paintEnrollmentCanvas(S.doc);select(null);chrome();
                    });
                }}
            ]);
        }
        function validateEnrollmentCanvas() {
            if(!enrollmentCanvasDirty())return true;
            var missing=enrollmentCanvas.find(function(step){return !step.title.trim()||!step.instruction.trim();});
            if(!missing)return true;
            S.doc.getElementById('basic-education-tab').click();
            var field=enrollmentCard(missing.step_id).querySelector('[data-ve-key="'+(!missing.title.trim()?'title':'instruction')+'"]');
            field.scrollIntoView({block:'center',behavior:'instant'});field.focus();
            toast('Add an office/title and instructions to each step before publishing.','error');return false;
        }
        function enrollmentChange(sel, file, reset) {
            if (S.busy) return;
            var previous = enrollmentChanges[sel.key];
            if (previous && previous.preview) URL.revokeObjectURL(previous.preview);
            if (reset && enrollmentImages[sel.key].url === enrollmentImages[sel.key].default) delete enrollmentChanges[sel.key];
            else enrollmentChanges[sel.key] = {reset:reset, file:file, preview:file ? previewUrl(file) : null};
            var change = enrollmentChanges[sel.key];
            sel.el.querySelector('img').src = change && change.preview ? change.preview : (reset ? enrollmentImages[sel.key].default : enrollmentImages[sel.key].url);
            renderToolbar(); chrome();
        }
        function enrollmentTools(sel) {
            return [
                {type:'chip',text:enrollmentImages[sel.key].label,icon:'image'},
                {icon:'image',label:'Change illustration',primary:true,run:function () {
                    pickFile('image/svg+xml,image/png,image/jpeg,image/webp,.svg,.png,.jpg,.jpeg,.webp',function (file) {
                        if (!/\.(svg|png|jpe?g|webp)$/i.test(file.name) || file.size > 5 * MB) { toast('Choose an SVG, PNG, JPG, or WEBP image of up to 5 MB.','error'); return; }
                        enrollmentChange(sel,file,false);
                    });
                }},
                {icon:'arrow-counterclockwise',label:'Restore Storyset illustration',run:function () { enrollmentChange(sel,null,true); }},
                {icon:'box-arrow-up-right',label:'Browse Storyset',run:function () {
                    toast('On Storyset, choose Download SVG. Then upload the .svg illustration here.','ok');
                    window.open('https://storyset.com/','_blank','noopener,noreferrer');
                }}
            ];
        }
        function publishEnrollmentCanvasChanges() {
            if (!Object.keys(enrollmentChanges).length && !enrollmentCanvasDirty()) return Promise.resolve(true);
            var payload={}, files={}, fields={};
            Object.keys(enrollmentChanges).forEach(function(key){
                payload[key]={reset:enrollmentChanges[key].reset,expected:enrollmentImages[key].url};
                if(enrollmentChanges[key].file)files['image_'+key]=enrollmentChanges[key].file;
            });
            fields.images=JSON.stringify(payload);
            if(enrollmentCanvasDirty()){fields.steps=JSON.stringify(enrollmentCanvas);fields.revision=enrollmentRevision;}
            return send('/admin/action/enrollment-canvas-handler.php',fields,files).then(function(res){
                if(!res.ok){toast(res.error,'error');return false;}
                Object.keys(enrollmentChanges).forEach(function(key){if(enrollmentChanges[key].preview)URL.revokeObjectURL(enrollmentChanges[key].preview);});
                enrollmentChanges={};enrollmentImages=res.images;enrollmentCanvas=res.steps;enrollmentBase=JSON.stringify(res.steps);enrollmentRevision=res.revision;
                paintEnrollmentCanvas(S.doc);
                // College illustrations use their existing card nodes.
                $$('[data-ve-img="enrollment-illustration"]',S.doc).forEach(function(el){var image=res.images[el.getAttribute('data-ve-key')];if(image)el.querySelector('img').src=image.url||image.default;});
                select(null);toast('Published. Your changes are live now.','ok');return true;
            });
        }
        function paintHistoryBackground(el, url, removed, pending) {
            var box = el.closest('.quote-banner-box');
            if (!box) return;
            var source = removed ? el.getAttribute('data-ve-default-image') : url;
            box.style.setProperty('--history-quote-background', 'url("' + source + '")');
            el.setAttribute('data-ve-has-image', removed ? '0' : '1');
            el.textContent = pending ? 'Background changed — click Publish' : 'Change background image';
        }
        function paintPhoto(el, change) {
            if (el.hasAttribute('data-ve-history-background')) {
                paintHistoryBackground(el, change.url, change.remove, true);
                return;
            }
            el.textContent = '';
            if (!change.remove) {
                var img = el.ownerDocument.createElement('img');
                img.src = change.url;
                img.alt = 'History photo preview';
                el.appendChild(img);
            }
            el.setAttribute('data-ve-has-image', change.remove ? '0' : '1');
            var hint = el.ownerDocument.createElement('span');
            hint.textContent = change.remove ? 'Photo removal pending ? click Publish' : 'Unsaved photo ? click Publish';
            hint.style.cssText = 'position:absolute;z-index:2;left:8px;right:8px;bottom:8px;padding:7px;background:#094024;color:white;text-align:center;font:600 13px/1.35 sans-serif';
            el.appendChild(hint);
        }
        function stagePhoto(sel, file) {
            if (photos[sel.key] && photos[sel.key].url) URL.revokeObjectURL(photos[sel.key].url);
            photos[sel.key] = { file: file, remove: !file, url: file ? previewUrl(file) : null };
            paintPhoto(sel.el, photos[sel.key]);
            renderToolbar(); chrome();
        }
        function publishPhotos() {
            return Object.keys(photos).reduce(function (promise, key) {
                return promise.then(function () {
                    var change = photos[key];
                    return sendContent({ action: 'save_section_image', page: 'history', section_key: key, remove_image: change.remove ? '1' : '0' }, { image: change.file }).then(function (res) {
                        if (!res.ok || (!change.remove && !res.image_url)) {
                            toast(res.error || 'The photo could not be published. Please try again.', 'error');
                            return;
                        }
                        var el = S.doc.querySelector('[data-ve-img="history:' + cssEsc(key) + '"]');
                        if (el && el.hasAttribute('data-ve-history-background')) {
                            paintHistoryBackground(el, res.image_url, change.remove, false);
                        } else if (el) {
                            el.textContent = '';
                            el.setAttribute('data-ve-has-image', change.remove ? '0' : '1');
                            if (!change.remove) {
                                var img = el.ownerDocument.createElement('img');
                                img.src = res.image_url;
                                img.alt = 'History photo';
                                el.appendChild(img);
                            } else {
                                var hint = el.ownerDocument.createElement('span');
                                hint.className = 'history-photo-hint';
                                hint.textContent = 'Click to add photo';
                                el.appendChild(hint);
                            }
                        }
                        if (change.url) URL.revokeObjectURL(change.url);
                        delete photos[key];
                        toast('Published. Your changes are live now.', 'ok');
                    });
                });
            }, Promise.resolve()).then(function () { renderToolbar(); });
        }
        var secs = (C.sections || []).map(function (s) {
            return { key: s.key, title: s.title, html: s.html || '', has_draft: !!s.has_draft, review: s.review_status || 'none',
                     note: s.review_note || '', by: s.submitted_by || '', el: null, base: '', dirty: false };
        });
        var admissionBaseOrders = {}, admissionUndo = [];
        function restoreAdmissionCategory(s) {
            var school=s.key.indexOf('custom_')===0?'college':s.key.split('_custom_')[0];
            var scope=S.doc.querySelector('[data-admission-scope="'+cssEsc(school)+'"]');if(!scope)return;
            var basic=school!=='college',tabId=(basic?'applicant-tab-':'tab-')+s.key,panelId=(basic?'applicant-panel-':'panel-')+s.key;
            var tab=h('button',{type:'button',class:'req-tab',role:'tab',id:tabId,'aria-controls':panelId,'aria-selected':'false',text:s.title});
            tab.setAttribute(basic?'data-basic-applicant':'data-target',s.key);
            var panel=S.doc.createElement('div');panel.className='req-panel';panel.id=panelId;panel.hidden=true;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',tabId);
            if(basic)panel.setAttribute('data-basic-applicant-panel',s.key);
            s.el=S.doc.createElement('div');s.el.className='admission-req-content';s.el.setAttribute('data-ve-section',s.key);s.el.setAttribute('data-ve-label',s.title);panel.appendChild(s.el);
            scope.querySelector('[data-admission-category-panels]').appendChild(panel);scope.querySelector('[data-ve-admission-add-category]').before(tab);
        }
        function locked(s) { return s.review === 'pending'; }
        function byEl(el) { for (var i = 0; i < secs.length; i++) if (secs[i].el === el) return secs[i]; return null; }
        function html(s) { return cleanHtml(s.el.innerHTML); }
        function plainSection(el) { return el.hasAttribute('data-ve-admission-label') || el.hasAttribute('data-ve-plain-section'); }

        function bind(s) {
            var el = s.el;
            var admissionLabel = plainSection(el);
            el.__ve = { rich: !admissionLabel, sec: s, max: admissionLabel ? (parseInt(el.getAttribute('data-ve-max'), 10) || (s.key === 'hero_intro' ? 2000 : 150)) : 0,
                multi: admissionLabel && (s.key === 'hero_intro' || el.hasAttribute('data-ve-multiline')), onChange: function () {
                s.dirty = !!s.isNew || html(s) !== s.base;
                admissionItemControls(s);
                markAdmissionChanges(s);
            } };
            if (plainSection(el)) {
                var plain = el.ownerDocument.createElement('div'); plain.innerHTML = s.html;
                el.textContent = plain.textContent;
            } else el.innerHTML = s.html || '<p></p>';
            el.setAttribute('data-ve-ph', 'Write here…');
            el.setAttribute('spellcheck', 'true');
            setLock(s);
            el.classList.toggle('ve-empty', !(el.textContent || '').trim());
            s.base = html(s); s.dirty = false;
            rememberAdmissionItems(s);
        }
        function setLock(s) {
            if (!s.el) return;
            s.el.setAttribute('contenteditable', locked(s) ? 'false' : (plainSection(s.el) ? PLAIN : 'true'));
            s.el.classList.toggle('ve-locked', locked(s));
            s.el.title = locked(s) ? 'Waiting for approval, so it can’t be changed right now.' : '';
            admissionItemControls(s);
        }
        function addExtras(doc) {
            var missing = secs.filter(function (s) { return !s.el; });
            if (!missing.length) return;
            var box = doc.createElement('div'); box.className = 've-extra';
            box.innerHTML = '<h3>More text for this page that the layout above doesn’t show</h3>';
            missing.forEach(function (s) {
                var w = doc.createElement('section'); w.style.cssText = 'background:#fff;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);padding:20px 24px;margin-bottom:18px';
                w.innerHTML = '<h4 style="margin:0 0 8px;font:700 1.1rem system-ui,sans-serif">' + esc(s.title) + '</h4>';
                var d = doc.createElement('div'); d.setAttribute('data-ve-section', s.key); w.appendChild(d); box.appendChild(w); s.el = d;
            });
            doc.body.appendChild(box);
        }
        function fallbackHtml() {
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + FB_CSS + '</style></head><body>' +
                '<header class="fb-hero"><small>Page text</small><h1>' + esc(C.page.title) + '</h1><p>This page’s design isn’t linked to the editor yet, so its text is shown here as simple blocks. Edit and publish as usual.</p></header><main>' +
                secs.map(function (s) { return '<section class="fb-sec"><h2>' + esc(s.title) + '</h2><div data-ve-section="' + esc(s.key) + '" data-ve-label="' + esc(s.title) + '"></div></section>'; }).join('') +
                '</main></body></html>';
        }

        function chipFor(s) {
            if (locked(s)) return { type: 'chip', tone: 'warn', icon: 'hourglass-split', text: 'Waiting for approval' };
            if (s.review === 'rejected') return { type: 'chip', tone: 'err', icon: 'arrow-counterclockwise', text: 'Changes requested', title: s.note || '' };
            if (s.dirty) return { type: 'chip', tone: 'warn', icon: 'pencil', text: 'Unsaved' };
            if (s.has_draft) return { type: 'chip', tone: 'warn', icon: 'file-earmark-text', text: 'Draft · not live' };
            return { type: 'chip', tone: 'ok', icon: 'broadcast', text: 'Live' };
        }

        function apply(s, res, replace) {
            s.isNew = false;
            s.has_draft = !!res.has_draft; s.review = res.review_status || 'none'; s.note = res.review_note || ''; s.by = res.submitted_by_name || '';
            if (replace && res.editor_html !== undefined) {
                s.html = res.editor_html;
                if (plainSection(s.el)) {
                    var plain = s.el.ownerDocument.createElement('div'); plain.innerHTML = res.editor_html;
                    s.el.textContent = plain.textContent;
                } else s.el.innerHTML = res.editor_html || '<p></p>';
                s.el.classList.toggle('ve-empty', !(s.el.textContent || '').trim());
            }
            s.base = html(s); s.dirty = false; setLock(s);
            rememberAdmissionItems(s); markAdmissionChanges(s);
        }
        function each(list, action) {
            var ok = 0, errs = [];
            return list.reduce(function (p, s) {
                return p.then(function () {
                    var request = s.isNew
                        ? send('/admin/action/admission-category-handler.php', { page: page, section_key: s.key, title: s.title, content: html(s) })
                        : sendReview({ action: action, page: page, section_key: s.key, content: html(s) });
                    return request.then(function (res) {
                        if (res.ok) { ok++; apply(s, res, action === 'discard'); } else errs.push(s.title + ': ' + res.error);
                    });
                });
            }, Promise.resolve()).then(function () { return { ok: ok, errs: errs }; });
        }
        function report(r, doneWord) {
            if (r.ok && !r.errs.length) toast(/^published/.test(doneWord) ? 'Published. Your changes are live now.' : /^saved/.test(doneWord) ? 'Draft saved. Your changes are not live yet.' : 'Submitted for approval. Your changes are not live yet.', 'ok');
            r.errs.forEach(function (m) { toast(m, 'error'); });
        }
        function publishable(s) { return !!s.el && !(s.isNew && s.removed) && (s.dirty || s.has_draft || locked(s)); }
        function submittable(s) { return !!s.el && !locked(s) && (s.dirty || (s.has_draft && s.review !== 'pending')); }
        function publishSections(list) {
            if (page === 'enrollment-procedures') {
                run('Publishing enrollment content...',function(){return publishEnrollmentCanvasChanges().then(function(ok){if(!ok)return;return each(list,'publish').then(function(r){report(r,'published. It is live now');});});});
                return;
            }
            if (S.doc.querySelector('[data-admission-scope]')) {
                run('Publishing…', function () {
                    var orders = admissionOrders();
                    var changes = list.map(function (s) { return {key:s.key,title:s.title,content:html(s),removed:!!s.removed}; });
                    return send('/admin/action/admission-category-handler.php',{action:'publish_page',page:page,sections:JSON.stringify(changes),orders:JSON.stringify(orders)}).then(function (res) {
                        if (!res.ok) { toast(res.error,'error'); return; }
                        list.forEach(function (s) {
                            if (s.removed) secs.splice(secs.indexOf(s),1);
                            else apply(s,res.sections[s.key],false);
                        });
                        secs = secs.filter(function (s) { return !s.removed; });
                        admissionBaseOrders = orders; admissionUndo = [];
                        secs.forEach(markAdmissionChanges); markAdmissionLayout();
                        toast('Published. Your changes are live now.','ok');
                    });
                });
                return;
            }
            run('Publishing…', function () { return each(list, 'publish').then(function (r) { report(r, 'published. It is live now'); return publishPhotos(); }); });
        }

        async function review(s, what) {
            if (what === 'approve') {
                if (window.ContentCompleteness && s.el && !await window.ContentCompleteness.review({ root: s.el, csrf: C.csrf })) return;
                if (!await window.AdminDialogs.confirm('Approve and publish this change?\n\nIt will go live on the website.')) return;
                return run('Publishing…', function () { return sendReview({ action: 'approve', page: page, section_key: s.key }).then(function (res) {
                    if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
            }
            askText({ label: 'Note to the editor (what should change?)', required: true, multiline: true, max: 500, cta: 'Send back', done: function (note) {
                run('Sending back…', function () { return sendReview({ action: 'reject', page: page, section_key: s.key, note: note }).then(function (res) {
                    if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
            } });
        }

        function admissionTabKey(tab) { return tab.getAttribute('data-basic-applicant') || tab.getAttribute('data-target'); }
        function admissionOrders() {
            var orders = {};
            $$('[data-admission-scope]',S.doc).forEach(function (scope) {
                orders[scope.getAttribute('data-admission-scope')] = $$('[role="tab"]',scope.querySelector('[data-admission-category-tabs]')).map(admissionTabKey);
            });
            return orders;
        }
        function admissionOrderCount() {
            if (!S.doc || !S.doc.querySelector('[data-admission-scope]')) return 0;
            var orders = admissionOrders();
            return Object.keys(orders).filter(function (key) { return !same(orders[key],admissionBaseOrders[key] || []); }).length;
        }
        function rememberAdmissionItems(s) {
            if (!s.el || !s.el.classList.contains('admission-req-content')) return;
            s.itemBase = new Map();
            $$('ol > li',s.el).forEach(function (item,index) {
                var text = item.querySelector('[data-ve-admission-item-text]');
                s.itemBase.set(item,{html:cleanHtml(text ? text.innerHTML : item.innerHTML),index:index});
            });
        }
        function markAdmissionChanges(s) {
            if (!s.el || !s.el.classList.contains('admission-req-content')) return;
            s.el.classList.toggle('ve-admission-unsaved',!!s.dirty);
            var heading = s.el.querySelector('h2');
            if (heading) {
                s.title = heading.textContent.trim();
                $$('[data-admission-category-tabs] [role="tab"]',S.doc).forEach(function (tab) { if (admissionTabKey(tab)===s.key && tab.textContent!==s.title) tab.textContent=s.title; });
            }
            $$('ol > li',s.el).forEach(function (item,index) {
                var base = s.itemBase && s.itemBase.get(item), text = item.querySelector('[data-ve-admission-item-text]');
                item.classList.toggle('ve-admission-item-unsaved',!!s.isNew || !base || base.index!==index || base.html!==cleanHtml(text ? text.innerHTML : item.innerHTML));
            });
            markAdmissionLayout();
        }
        function markAdmissionLayout() {
            if (!S.doc) return;
            $$('[data-admission-scope]',S.doc).forEach(function (scope) {
                var name = scope.getAttribute('data-admission-scope'), baseline = admissionBaseOrders[name] || [];
                $$('[role="tab"]',scope.querySelector('[data-admission-category-tabs]')).forEach(function (tab,index) {
                    var key = admissionTabKey(tab), s = secs.find(function (row) { return row.key===key; });
                    var wrapper = tab.closest('.ve-admission-category');
                    if (wrapper) wrapper.classList.toggle('is-unsaved',baseline[index]!==key || !!(s && s.dirty));
                });
                var banner = scope.querySelector('[data-ve-admission-undo-banner]');
                if (!banner) return;
                var record = admissionUndo.filter(function (row) { return row.scope===scope; }).at(-1);
                banner.hidden = !record;
                if (record) banner.querySelector('span').textContent = record.label + (C.canPublish ? ' Publish to save.' : ' Save or submit to keep this change.');
            });
        }
        function pushAdmissionUndo(scope,label,restore) { admissionUndo.push({scope:scope,label:label,restore:restore}); markAdmissionLayout(); }
        function undoAdmissionDeletion(scope) {
            var index = admissionUndo.findLastIndex(function (row) { return row.scope===scope; });
            if (index<0) return;
            admissionUndo.splice(index,1)[0].restore(); markAdmissionLayout(); chrome();
        }
        function admissionItemControls(s) {
            if (!s.el || !s.el.classList.contains('admission-req-content')) return;
            if (locked(s)) {
                $$('[data-ve-admission-item-tools]', s.el).forEach(function (tools) { tools.remove(); });
                $$('[data-ve-admission-item-text]', s.el).forEach(function (text) { text.tabIndex = -1; });
                return;
            }
            $$('ol > li', s.el).forEach(function (item) {
                var text = item.querySelector(':scope > [data-ve-admission-item-text]');
                if (!text) {
                    text = S.doc.createElement('span');
                    text.className = 've-admission-item-text';
                    text.setAttribute('data-ve-admission-item-text','');
                    text.setAttribute('tabindex','0');
                    text.setAttribute('role','textbox');
                    text.setAttribute('aria-label','Checklist item');
                    text.setAttribute('title','Click to edit checklist item');
                    Array.from(item.childNodes).forEach(function (child) {
                        if (child.nodeType !== 1 || !child.hasAttribute('data-ve-admission-item-tools')) text.appendChild(child);
                    });
                    item.prepend(text);
                }
                text.tabIndex = 0;
                if (item.querySelector('[data-ve-admission-item-tools]')) return;
                var tools = h('span', {class:'ve-admission-item-tools','data-ve-admission-item-tools':'',contenteditable:'false'}, [
                    h('button',{type:'button','data-ve-admission-drag-item':'',title:'Drag to reorder, or use Up and Down arrow keys','aria-label':'Reorder checklist item'},[icon('grip-vertical')]),
                    h('button',{type:'button','data-ve-admission-delete-item':'',title:'Delete checklist item','aria-label':'Delete checklist item'},[icon('trash'),h('span',{text:'Delete'})])
                ]);
                item.appendChild(tools);
            });
        }
        function deleteAdmissionItem(button) {
            var item = button.closest('li');
            var s = byEl(sectionElOf(item));
            if (!s || locked(s)) return;
            var list = item.parentNode, next = item.nextSibling, scope = s.el.closest('[data-admission-scope]');
            item.remove(); s.el.__ve.onChange(); select(null);
            pushAdmissionUndo(scope,'Checklist item deleted.',function () { list.insertBefore(item,next && next.parentNode===list ? next : null); s.el.__ve.onChange(); });
            chrome();
        }
        function admissionControls(scope) {
            if (!scope) return;
            $$('[data-ve-section]', scope).forEach(function (el) {
                var s = secs.find(function (item) { return item.key === el.getAttribute('data-ve-section'); });
                if (!s || locked(s)) return;
                admissionItemControls(s);
                if (el.parentNode.querySelector('[data-ve-admission-add-item]')) return;
                el.after(h('button', { type: 'button', class: 've-admission-add', 'data-ve-admission-add-item': s.key, text: '+ Add checklist item' }));
            });
            var tabs = scope.querySelector('[data-admission-category-tabs]');
            if (C.canPublish && tabs) $$('[role="tab"]',tabs).forEach(function (tab) {
                if (tab.closest('.ve-admission-category')) return;
                var key = admissionTabKey(tab), wrapper = h('span',{class:'ve-admission-category','data-ve-admission-category':key});
                tab.before(wrapper); wrapper.appendChild(tab);
                wrapper.appendChild(h('span',{class:'ve-admission-category-tools'},[
                    h('button',{type:'button','data-ve-admission-drag-category':key,title:'Drag to reorder, or use Left and Right arrow keys','aria-label':'Reorder category'},[icon('grip-vertical')]),
                    h('button',{type:'button','data-ve-admission-rename-category':key,title:'Rename category','aria-label':'Rename category'},[icon('pencil')]),
                    h('button',{type:'button','data-ve-admission-delete-category':key,title:'Delete category','aria-label':'Delete category'},[icon('trash')])
                ]));
            });
            if (C.canPublish && tabs && !tabs.querySelector('[data-ve-admission-add-category]')) {
                tabs.appendChild(h('button', {type:'button',class:'ve-admission-add','data-ve-admission-add-category':'',text:'+ Add category'}));
            }
            if (!scope.querySelector('[data-ve-admission-undo-banner]')) {
                var banner = h('div',{class:'ve-admission-undo','data-ve-admission-undo-banner':'',hidden:true,role:'status'},[
                    h('span',{text:''}),h('button',{type:'button','data-ve-admission-undo':'',text:'Undo deletion'})
                ]);
                tabs.after(banner);
            }
        }
        function renameAdmissionCategory(key,anchor) {
            var s = secs.find(function (row) { return row.key===key; });
            if (!C.canPublish || !s || locked(s)) return;
            askText({label:'Category name',anchor:anchor,value:s.el.querySelector('h2')?.textContent || s.title,required:true,max:150,cta:'Rename category',done:function (title) {
                var heading=s.el.querySelector('h2');
                if (!heading) { heading=S.doc.createElement('h2');s.el.prepend(heading); }
                heading.textContent=title;s.title=title;s.el.__ve.onChange();chrome();
            }});
        }
        function deleteAdmissionCategory(key) {
            var s=secs.find(function (row) { return row.key===key; });
            if (!C.canPublish || !s || locked(s)) return;
            var scope=s.el.closest('[data-admission-scope]'),panel=s.el.closest('.req-panel');
            var tab=S.doc.getElementById(panel.getAttribute('aria-labelledby')),wrapper=tab.closest('.ve-admission-category');
            var nextPanel=panel.nextSibling,nextTab=wrapper.nextSibling,panels=panel.parentNode,tabs=wrapper.parentNode;
            panel.remove();wrapper.remove();s.removed=true;s.dirty=true;select(null);
            var first=tabs.querySelector('[role="tab"]');if(first)first.click();
            pushAdmissionUndo(scope,'Category deleted.',function () {
                panels.insertBefore(panel,nextPanel && nextPanel.parentNode===panels ? nextPanel : null);
                tabs.insertBefore(wrapper,nextTab && nextTab.parentNode===tabs ? nextTab : null);
                s.removed=false;s.dirty=!!s.isNew || html(s)!==s.base;tab.click();markAdmissionChanges(s);
            });
            markAdmissionLayout();chrome();
        }
        function bindAdmissionReordering(doc) {
            var drag=null;
            function handleOf(target) { return target.closest('[data-ve-admission-drag-item],[data-ve-admission-drag-category]'); }
            function sourceOf(handle) { return handle.hasAttribute('data-ve-admission-drag-item') ? handle.closest('li') : handle.closest('.ve-admission-category'); }
            function changed(source) {
                if (source.matches('li')) { var s=byEl(sectionElOf(source));if(s)s.el.__ve.onChange(); }
                markAdmissionLayout();chrome();
            }
            function move(source,target) {
                if (!target || target===source || target.parentNode!==source.parentNode) return;
                var siblings=Array.from(source.parentNode.children),after=siblings.indexOf(source)<siblings.indexOf(target);
                source.parentNode.insertBefore(source,after?target.nextSibling:target);changed(source);
            }
            doc.addEventListener('pointerover',function (event) {
                if (handleOf(event.target) && S.sel) select(null);
            });
            doc.addEventListener('pointerdown',function (event) {
                var handle=handleOf(event.target);if(!handle || S.preview || S.busy || event.button!==0)return;
                var source=sourceOf(handle),s=source.matches('li') && byEl(sectionElOf(source));
                if(s && locked(s))return;
                event.preventDefault();select(null);drag={source:source,x:event.clientX,y:event.clientY,id:event.pointerId,target:null,started:false};
                try{handle.setPointerCapture(event.pointerId);}catch(_){}
            });
            doc.addEventListener('pointermove',function (event) {
                if(!drag || drag.id!==event.pointerId)return;
                if(!drag.started && Math.hypot(event.clientX-drag.x,event.clientY-drag.y)<5)return;
                drag.started=true;drag.source.classList.add('ve-admission-dragging');event.preventDefault();
                var hovered=doc.elementFromPoint(event.clientX,event.clientY);
                var target=hovered && hovered.closest(drag.source.matches('li')?'ol > li':'.ve-admission-category');
                if(!target && hovered && !drag.source.contains(hovered)) {
                    var best=24;
                    Array.from(drag.source.parentNode.children).forEach(function (candidate) {
                        if(candidate===drag.source || !candidate.matches(drag.source.matches('li')?'li':'.ve-admission-category'))return;
                        var rect=candidate.getBoundingClientRect(),dx=Math.max(rect.left-event.clientX,0,event.clientX-rect.right),dy=Math.max(rect.top-event.clientY,0,event.clientY-rect.bottom),distance=Math.hypot(dx,dy);
                        if(distance<best){best=distance;target=candidate;}
                    });
                }
                if(drag.target)drag.target.classList.remove('ve-admission-drop-target');
                drag.target=target && target!==drag.source && target.parentNode===drag.source.parentNode?target:null;
                if(drag.target)drag.target.classList.add('ve-admission-drop-target');
                if(event.clientY<70)doc.defaultView.scrollBy(0,-14);
                if(event.clientY>doc.defaultView.innerHeight-55)doc.defaultView.scrollBy(0,14);
            });
            function finish(event) {
                if(!drag || (event && event.pointerId!==drag.id))return;
                var current=drag;drag=null;current.source.classList.remove('ve-admission-dragging');
                if(current.target)current.target.classList.remove('ve-admission-drop-target');
                if(event && event.type==='pointerup' && current.started)move(current.source,current.target);
            }
            doc.addEventListener('pointerup',finish);doc.addEventListener('pointercancel',finish);
            doc.addEventListener('keydown',function (event) {
                var handle=handleOf(event.target);if(!handle || S.preview || S.busy)return;
                var source=sourceOf(handle),item=source.matches('li'),direction=item?{ArrowUp:-1,ArrowDown:1}:{ArrowLeft:-1,ArrowRight:1};
                if(!direction[event.key])return;
                event.preventDefault();var siblings=Array.from(source.parentNode.children).filter(function (node) { return node.matches(item?'li':'.ve-admission-category'); });
                move(source,siblings[siblings.indexOf(source)+direction[event.key]]);handle.focus();
            });
        }
        function addAdmissionItem(key, anchor) {
            var s = secs.find(function (item) { return item.key === key; });
            if (!s || locked(s)) return;
            askText({label:'Checklist item',anchor:anchor,required:true,multiline:true,max:2000,cta:'Add item',done:function (text) {
                var list = s.el.querySelector('ol');
                if (!list) { list = S.doc.createElement('ol'); s.el.appendChild(list); }
                var item = S.doc.createElement('li'); item.textContent = text; list.appendChild(item);
                s.el.__ve.onChange(); select(null); item.scrollIntoView({block:'center',behavior:'instant'}); chrome();
            }});
        }
        function addAdmissionCategory(scope, anchor) {
            if (!C.canPublish) return;
            askText({label:'Category name',anchor:anchor,required:true,max:150,cta:'Add category',done:function (title) {
                var school = scope.getAttribute('data-admission-scope');
                var token = Array.from(crypto.getRandomValues(new Uint8Array(8))).map(function (value) { return value.toString(16).padStart(2,'0'); }).join('');
                var key = (school === 'college' ? '' : school + '_') + 'custom_' + token;
                var basic = school !== 'college';
                var tabId = (basic ? 'applicant-tab-' : 'tab-') + key;
                var panelId = (basic ? 'applicant-panel-' : 'panel-') + key;
                var tab = h('button',{type:'button',class:'req-tab',role:'tab',id:tabId,'aria-controls':panelId,'aria-selected':'false',text:title});
                tab.setAttribute(basic ? 'data-basic-applicant' : 'data-target', key);
                var panel = S.doc.createElement('div'); panel.className = 'req-panel'; panel.id = panelId; panel.hidden = true;
                panel.setAttribute('role','tabpanel'); panel.setAttribute('aria-labelledby',tabId);
                if (basic) panel.setAttribute('data-basic-applicant-panel',key);
                var el = S.doc.createElement('div'); el.className = 'admission-req-content';
                el.setAttribute('data-ve-section',key); el.setAttribute('data-ve-label',title); panel.appendChild(el);
                scope.querySelector('[data-admission-category-panels]').appendChild(panel);
                scope.querySelector('[data-ve-admission-add-category]').before(tab);
                var s = {key:key,title:title,html:'<h2>'+esc(title)+'</h2><p>Enrollment requirements</p><ol></ol>',has_draft:false,review:'none',note:'',by:'',el:el,base:'',dirty:true,isNew:true};
                secs.push(s); bind(s); s.dirty = true; admissionControls(scope);
                tab.click(); select(null); s.el.scrollIntoView({block:'center',behavior:'instant'}); markAdmissionChanges(s); chrome();
            }});
        }
        function removeNewAdmissionCategory(s) {
            var scope = s.el.closest('[data-admission-scope]');
            var panel = s.el.closest('.req-panel');
            var tab = S.doc.getElementById(panel.getAttribute('aria-labelledby'));
            panel.remove(); if (tab) (tab.closest('.ve-admission-category') || tab).remove(); secs.splice(secs.indexOf(s),1);
            var first = scope.querySelector('[role="tab"]'); if (first) first.click();
            select(null); markAdmissionLayout(); chrome();
        }

        var A = {
            outline: true,
            startFallback: function () { loadSrcdoc(fallbackHtml()); },
            draftState: function (value) {
                if (!value) return {secs:secs.map(function(s){return Object.assign({},s,{html:s.el&&!s.removed?html(s):s.html});}),photos:photos,enrollmentImages:enrollmentImages,enrollmentChanges:enrollmentChanges,enrollmentCanvas:enrollmentCanvas,enrollmentBase:enrollmentBase,enrollmentRevision:enrollmentRevision,admissionBaseOrders:admissionBaseOrders,orders:admissionOrders()};
                photos=value.photos;enrollmentImages=value.enrollmentImages;enrollmentChanges=value.enrollmentChanges;enrollmentCanvas=value.enrollmentCanvas;enrollmentBase=value.enrollmentBase;enrollmentRevision=value.enrollmentRevision;admissionBaseOrders=value.admissionBaseOrders;
                secs=value.secs.map(function(s){s.el=S.doc.querySelector('[data-ve-section="'+cssEsc(s.key)+'"]');return s;});
                secs.forEach(function(s){
                    if(!s.el&&s.isNew)restoreAdmissionCategory(s);
                    if(s.removed&&s.el){var panel=s.el.closest('.req-panel'),tab=panel&&S.doc.getElementById(panel.getAttribute('aria-labelledby'));if(panel)panel.remove();if(tab)(tab.closest('.ve-admission-category')||tab).remove();}
                });
                paintEnrollmentCanvas(S.doc);
            },
            draftRebind: function () {
                secs.forEach(function(s){if(!s.el||s.removed)return;var base=s.base,dirty=s.dirty;bind(s);s.base=base;s.dirty=dirty;});
                $$('[data-admission-scope]',S.doc).forEach(admissionControls);
                secs.forEach(markAdmissionChanges);
                Object.keys(photos).forEach(function(key){var el=S.doc.querySelector('[data-ve-img="history:'+cssEsc(key)+'"]');if(el)paintPhoto(el,photos[key]);});
                Object.keys(enrollmentChanges).forEach(function(key){var el=S.doc.querySelector('[data-ve-img="enrollment-illustration"][data-ve-key="'+cssEsc(key)+'"] img');if(el)el.src=enrollmentChanges[key].preview||enrollmentImages[key].default;});
            },
            mount: function (doc) {
                try { enrollmentImages=JSON.parse(doc.getElementById('veEnrollmentIllustrations').textContent); } catch (_) { enrollmentImages={}; }
                if (enrollmentCanvas === null && doc.getElementById('veEnrollmentSteps')) {
                    var canvas=JSON.parse(doc.getElementById('veEnrollmentSteps').textContent);
                    enrollmentCanvas=canvas.steps;enrollmentBase=JSON.stringify(canvas.steps);enrollmentRevision=canvas.revision;
                }
                paintEnrollmentCanvas(doc);

                Object.keys(enrollmentChanges).forEach(function (key) {
                    var el=doc.querySelector('[data-ve-img="enrollment-illustration"][data-ve-key="'+cssEsc(key)+'"] img');
                    if (el) el.src=enrollmentChanges[key].preview || enrollmentImages[key].default;
                });
                var found = 0;
                secs.forEach(function (s) { s.el = doc.querySelector('[data-ve-section="' + cssEsc(s.key) + '"]'); if (s.el) found++; });
                if (!found && !S.fallback && !doc.querySelector('[data-admission-scope]')) { loadSrcdoc(fallbackHtml()); return true; }
                addExtras(doc);
                secs.forEach(bind);
                $$('[data-admission-scope]', doc).forEach(admissionControls);
                admissionBaseOrders=admissionOrders();admissionUndo=[];secs.forEach(markAdmissionChanges);
                if(doc.querySelector('[data-admission-scope]'))bindAdmissionReordering(doc);
                if (doc.querySelector('[data-admission-scope]')) doc.addEventListener('selectionchange', function () {
                    var selection = doc.defaultView.getSelection(), node = selection.anchorNode;
                    var selected = node && (node.nodeType === 1 ? node : node.parentElement);
                    selected = selected && selected.closest('[data-ve-admission-item-text]');
                    $$('[data-ve-admission-item-text]', doc).forEach(function (text) { text.classList.toggle('is-editing', text === selected); });
                });
                Object.keys(photos).forEach(function (key) {
                    var el = doc.querySelector('[data-ve-img="history:' + cssEsc(key) + '"]');
                    if (el) paintPhoto(el, photos[key]);
                });
                return false;
            },
            afterMount: function () {
                if (S.fallback && !A.toldFallback) { A.toldFallback = true; toast('This page’s design isn’t linked to the editor yet, so its text is shown as simple blocks.', 'ok'); }
                if (C.focusSection) { var s = secs.filter(function (x) { return x.key === C.focusSection; })[0]; C.focusSection = null; if (s) A.focus(s); }
            },
            focus: function (s) {
                if (!s.el) return;
                s.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                s.el.classList.add('ve-flash'); window.setTimeout(function () { s.el.classList.remove('ve-flash'); }, 1400);
                select({ kind: 'rich', el: s.el, sec: s, noCaret: locked(s), prevent: false });
            },
            pick: function (t, event) {
                if (t.closest('[data-ve-enrollment-add]')) { addEnrollmentCard(); return {kind:'none'}; }
                if(t.closest('[data-ve-admission-drag-item],[data-ve-admission-drag-category]'))return {kind:'none'};
                var undo=t.closest('[data-ve-admission-undo]');if(undo){undoAdmissionDeletion(undo.closest('[data-admission-scope]'));return {kind:'none'};}
                var rename=t.closest('[data-ve-admission-rename-category]');if(rename){renameAdmissionCategory(rename.getAttribute('data-ve-admission-rename-category'),rename);return {kind:'none'};}
                var remove=t.closest('[data-ve-admission-delete-category]');if(remove){deleteAdmissionCategory(remove.getAttribute('data-ve-admission-delete-category'));return {kind:'none'};}
                var deleteItem = t.closest('[data-ve-admission-delete-item]');
                if (deleteItem) { deleteAdmissionItem(deleteItem); return {kind:'none'}; }
                var addItem = t.closest('[data-ve-admission-add-item]');
                if (addItem) { addAdmissionItem(addItem.getAttribute('data-ve-admission-add-item'), addItem); return {kind:'none'}; }
                var addCategory = t.closest('[data-ve-admission-add-category]');
                if (addCategory) { addAdmissionCategory(addCategory.closest('[data-admission-scope]'), addCategory); return {kind:'none'}; }
                var label = t.closest('[data-ve-admission-label],[data-ve-plain-section]');
                if (label) {
                    var labelSection = byEl(label);
                    if (labelSection) {
                        if (!locked(labelSection)) {
                            // Form labels and buttons otherwise transfer focus away from their editable text.
                            if (event && label.closest('label,[type="submit"]')) event.preventDefault();
                            if (S.doc.activeElement !== label) {
                                label.focus({ preventScroll: true });
                                var caret = event && S.doc.caretRangeFromPoint && S.doc.caretRangeFromPoint(event.clientX, event.clientY);
                                if (caret && label.contains(caret.startContainer)) {
                                    var selection = S.win.getSelection(); selection.removeAllRanges(); selection.addRange(caret);
                                }
                            }
                        }
                        return { kind: 'admission-label', el: label, sec: labelSection, prevent: false, noCaret: locked(labelSection) };
                    }
                }
                var illustration=t.closest('[data-ve-img="enrollment-illustration"]');
                if (illustration && enrollmentImages[illustration.getAttribute('data-ve-key')]) return {kind:'enrollment-illustration',el:illustration,key:illustration.getAttribute('data-ve-key'),prevent:true};
                var photo = t && t.closest ? t.closest('[data-ve-img^="history:"]') : null;
                if (photo) return { kind: 'history-image', el: photo, key: photo.getAttribute('data-ve-img').slice(8), prevent: true };
                var stepCard=t.closest('[data-ve-enrollment-step]');
                if(stepCard){var stepField=fieldOf(t);return {kind:stepField?'enrollment-step-field':'enrollment-step',el:stepField||stepCard,id:stepCard.getAttribute('data-ve-enrollment-step'),prevent:!stepField};}
                var el = sectionElOf(t); var s = el && byEl(el);
                var inlineText = t.closest('[data-ve-admission-item-text]');
                if (s && inlineText && !locked(s)) {
                    $$('[data-ve-admission-item-text].is-editing', S.doc).forEach(function (text) { text.classList.remove('is-editing'); });
                    inlineText.classList.add('is-editing');
                }
                return s ? { kind: 'rich', el: el, sec: s, prevent: false, noCaret: locked(s) } : null;
            },
            toolbar: function (sel) {
                if (sel.kind === 'enrollment-step-field' || sel.kind === 'enrollment-step') return enrollmentStepTools(sel.id,true);
                if (sel.kind === 'enrollment-illustration') return enrollmentTools(sel).concat(sel.key.indexOf('basic-')===0?enrollmentStepTools(sel.key.slice(6),false):[]);
                var s = sel.sec, it = [];
                if (sel.kind === 'history-image') {
                    var key = sel.key, hasImage = sel.el.getAttribute('data-ve-has-image') === '1';
                    if (sel.el.hasAttribute('data-ve-history-background')) {
                        it.push({ type: 'chip', icon: 'image', text: photos[key] ? 'Unsaved background change' : 'Quote background' });
                        it.push({ icon: 'image', label: 'Change background image', primary: true, run: function () {
                            pickFile('image/jpeg,image/png,image/webp', function (file) {
                                if (!S.busy) stagePhoto(sel, file);
                            }, false, sel.el.closest('.quote-banner-box'));
                        } });
                        if (hasImage) it.push({ icon: 'arrow-counterclockwise', label: 'Restore default background', run: function () { stagePhoto(sel, null); } });
                        return it;
                    }
                    it.push({ type: 'chip', icon: 'image', text: photos[key] ? 'Unsaved photo change' : (hasImage ? 'History photo' : 'Empty photo slot') });
                    it.push({ icon: 'image', label: hasImage ? 'Replace photo' : 'Add photo', primary: true, run: function () {
                        pickFile('image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp', function (file) {
                            if (S.busy) return;
                            if (file.size > 5 * MB) { toast('That photo is over 5 MB.', 'error'); return; }
                            stagePhoto(sel, file);
                        });
                    } });
                    if (hasImage) it.push({ icon: 'trash', title: 'Remove this photo', danger: true, run: function () {
                        confirmPop('Remove this photo?', 'The photo will be removed from the website when you click Publish.', 'Remove photo', function () {
                            stagePhoto(sel, null);
                        });
                    } });
                    return it;
                }
                if (!locked(s) && sel.kind !== 'admission-label') it = richTextTools();
                it.push(chipFor(s));
                if (locked(s) && C.canApprove) {
                    it.push({ icon: 'check2-circle', label: 'Approve & publish', primary: true, run: function () { review(s, 'approve'); } });
                    it.push({ icon: 'arrow-counterclockwise', label: 'Request changes', run: function () { review(s, 'reject'); } });
                } else if (locked(s)) {
                    it.push({ icon: 'arrow-90deg-left', label: 'Take back', title: 'Take this back so you can keep editing', run: function () {
                        run('Taking back…', function () { return sendReview({ action: 'withdraw', page: page, section_key: s.key }).then(function (res) { if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); }); } });
                } else if (s.dirty || s.has_draft) {
                    it.push({ icon: 'arrow-counterclockwise', title: s.has_draft ? 'Throw away my draft and go back to what is live' : 'Undo my unsaved changes', run: async function () {
                        if (!await window.AdminDialogs.confirm(s.has_draft ? 'Discard your draft and go back to what is on the website?' : 'Undo your unsaved changes in this section?')) return;
                        if (s.isNew) { removeNewAdmissionCategory(s); return; }
                        if (!s.has_draft) { s.el.innerHTML = s.base || '<p></p>'; s.dirty = false; admissionItemControls(s); rememberAdmissionItems(s); markAdmissionChanges(s); renderToolbar(); chrome(); return; }
                        run('Discarding…', function () { return sendReview({ action: 'discard', page: page, section_key: s.key }).then(function (res) { if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
                    } });
                }
                return it;
            },
            dirtyCount: function () { return secs.filter(function (s) { return s.dirty && !(s.isNew && s.removed); }).length + mediaChangeCount() + admissionOrderCount(); },
            status: function () {
                var n = A.dirtyCount(); if (n) return { text: plural(n, 'change') + ' not saved', tone: 'warn' };
                var p = secs.filter(locked).length; if (p) return { text: plural(p, 'section') + ' waiting for approval', tone: 'warn' };
                var d = secs.filter(function (s) { return s.has_draft; }).length; if (d) return { text: plural(d, 'draft') + ' saved · not live yet', tone: 'warn' };
                return { text: 'Everything here is live', tone: 'ok' };
            },
            actions: function () {
                var n = A.dirtyCount();
                var secondary = { label: 'Save draft', icon: 'save', disabled: n === 0 || mediaChangeCount() > 0, title: mediaChangeCount() ? 'Image changes stay in this preview until Publish. Draft saving is available for text only.' : 'Keep your changes privately. Visitors cannot see them yet.' };
                if (enrollmentCanvasDirty()) { secondary.disabled=true;secondary.title='Enrollment step changes stay in this preview until Publish.'; }
                if (secs.some(function (s) { return s.isNew; })) { secondary.disabled = true; secondary.title = 'New categories stay in this preview until Publish.'; }
                if (admissionOrderCount() || secs.some(function(s){return s.removed;})) { secondary.disabled=true;secondary.title='Category layout changes stay in this preview until Publish.'; }
                if (C.canPublish) return { secondary: secondary, primary: { label: 'Publish', icon: 'cloud-upload', disabled: !secs.some(publishable) && !mediaChangeCount() && !admissionOrderCount(), title: 'Put your changes on the live website' } };
                return { secondary: secondary, primary: { label: 'Submit for approval', icon: 'send', disabled: !C.reviewReady || !secs.some(submittable), title: C.reviewReady ? 'Send your changes to an administrator' : 'Approval has not been switched on yet' } };
            },
            runSecondary: function () {
                if (mediaChangeCount()) return;
                var list = secs.filter(function (s) { return s.dirty && !locked(s); }); if (!list.length) return;
                run('Saving draft…', function () { return each(list, 'save_draft').then(function (r) { report(r, 'saved as a draft (visitors cannot see it yet)'); }); });
            },
            runPrimary: function () {
                if (C.canPublish) {
                    var list = secs.filter(publishable), count = list.length + mediaChangeCount() + admissionOrderCount(); if (!count) return;
                    confirmPublish(plural(count, 'change') + ' will go live on the website.', function () { if (!S.allowIncomplete && !validateEnrollmentCanvas()) return; publishSections(list); });
                    return;
                    run('Publishing…', function () { return each(list, 'publish').then(function (r) { report(r, 'published. It is live now'); }); });
                } else {
                    var l2 = secs.filter(submittable); if (!l2.length) return;
                    run('Sending…', function () { return each(l2, 'submit').then(function (r) { report(r, 'sent for approval'); }); });
                }
            },
            openOutline: function (x, y) {
                var box = h('div', { class: 've-outline' }, [h('div', { class: 've-pop-label', text: 'Sections on this page' })].concat(secs.filter(function(s){return !s.removed;}).map(function (s) {
                    var c = chipFor(s);
                    return h('button', { type: 'button', class: 've-outline-row', on: { click: function () { closePop(); A.focus(s); } } }, [
                        h('span', { class: 've-dot ve-dot-' + (c.tone || 'ok') }), h('strong', { text: s.title }), h('small', { text: c.text })]);
                })));
                openPop(box, x, y);
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: homepage (slideshow + president message) ══════════════════
    function safeLink(v) {
        v = String(v || '').trim();
        if (!v) return '';
        if (/^\s*(javascript|data|vbscript):/i.test(v)) return null;
        return v;
    }
    function normMsg(s) {
        return String(s == null ? '' : s).replace(/\r\n?/g, '\n').split(/\n{2,}/).map(function (p) { return p.trim(); }).filter(Boolean).join('\n\n');
    }

    function HomeAdapter() {
        var HD = C.home || { slides: [], president: {} };
        var slides = [], cur = 0, wantCur = null, orderDirty = false;
        var PKEYS = { 'pres-name': 'name', 'pres-title': 'title', 'pres-org': 'org', 'pres-eyebrow': 'eyebrow', 'pres-heading': 'heading', 'pres-greeting': 'greeting', 'pres-message': 'message' };
        var pres = {}, presOrig = {}, presFile = null, presRemove = false, presImg = null;
        var dock = $('#veDock'), emptyBox = $('#veEmpty');
        // headings, intro lines and the Academic Programs cards (stored by key, see saveHomeContent() in admin-functions.php)
        var HC = {}, hc = {}, hcFiles = {}, hcReset = {};
        function loadContent(c) { HC = c || {}; hc = {}; hcFiles = {}; hcReset = {}; Object.keys(HC).forEach(function (k) { hc[k] = HC[k].value; }); }
        loadContent(HD.content);
        function hcTextKeys() { return Object.keys(HC).filter(function (k) { return HC[k].type !== 'image' && hc[k] !== HC[k].value; }); }
        function hcCount() { return hcTextKeys().length + Object.keys(hcFiles).length + Object.keys(hcReset).length; }
        function paintHomeImage(sel, url) {
            if (sel.area) {
                sel.area.style.setProperty('--home-section-background', 'url(' + JSON.stringify(url) + ')');
                sel.el.setAttribute('data-ve-background-value', url);
            } else {
                setBg(sel.el, url);
            }
        }

        function snap(o) {
            return { title: o.title, subtitle: o.subtitle, btn1_text: o.btn1_text, btn1_link: o.btn1_link, btn2_text: o.btn2_text, btn2_link: o.btn2_link,
                     status: o.status, show_on_mobile: o.show_on_mobile, show_gradient: o.show_gradient };
        }
        function norm(s) {
            var o = {
                id: +s.slide_id, title: s.title || '', subtitle: s.subtitle || '', btn1_text: s.btn1_text || '', btn1_link: s.btn1_link || '',
                btn2_text: s.btn2_text || '', btn2_link: s.btn2_link || '', status: s.status === 'draft' ? 'draft' : 'published',
                show_on_mobile: (s.show_on_mobile == null || +s.show_on_mobile) ? 1 : 0, show_gradient: (s.show_gradient == null || +s.show_gradient) ? 1 : 0,
                media_type: s.media_type === 'video' ? 'video' : 'image', media_path: s.media_path || '', media_path_mobile: s.media_path_mobile || '',
                file: null, mfile: null, removeMobile: false, el: null, _url: '', _murl: ''
            };
            o.orig = snap(o); return o;
        }
        function loadSlides(list) { slides = (list || []).map(norm); orderDirty = false; }
        function loadPresident(p) {
            pres = {}; Object.keys(PKEYS).forEach(function (k) { pres[PKEYS[k]] = String((p || {})[PKEYS[k]] == null ? '' : p[PKEYS[k]]); });
            pres.message = normMsg(pres.message);
            presOrig = JSON.parse(JSON.stringify(pres)); presFile = null; presRemove = false;
            pres.photo_url = (p || {}).photo_url || '';
        }
        loadSlides(HD.slides); loadPresident(HD.president);

        function slideDirty(s) { return !same(snap(s), s.orig) || !!s.file || !!s.mfile || s.removeMobile; }
        function presDirty() {
            return Object.keys(PKEYS).some(function (k) { var f = PKEYS[k]; return String(pres[f]) !== String(presOrig[f]); }) || !!presFile || presRemove;
        }
        function slideOfEl(el) { var se = el.closest('[data-ve-slide]'); for (var i = 0; i < slides.length; i++) if (slides[i].el === se) return slides[i]; return null; }
        function idx(s) { return slides.indexOf(s); }

        // ── reading what was typed on a slide ──
        function readSlide(s) {
            function val(f) { var e = s.el.querySelector('[data-ve-field="' + f + '"]'); return e ? readField(e) : ''; }
            var t1 = val('slide-title1'), t2 = val('slide-title2');
            s.title = (t1 && t2) ? t1 + '|' + t2 : (t1 || t2);
            s.subtitle = val('slide-subtitle'); s.btn1_text = val('slide-btn1'); s.btn2_text = val('slide-btn2');
            ghosts(s);
        }
        function ghosts(s) {
            [1, 2].forEach(function (n) { var a = s.el.querySelector('[data-ve-btn="' + n + '"]'); if (a) a.classList.toggle('ve-ghost', !s['btn' + n + '_text']); });
        }
        function bindSlide(s) {
            if (!s.el) return;
            ['slide-title1', 'slide-title2', 'slide-subtitle', 'slide-btn1', 'slide-btn2'].forEach(function (f) {
                var fe = s.el.querySelector('[data-ve-field="' + f + '"]');
                if (fe) makeField(fe, function () { readSlide(s); });
            });
            [1, 2].forEach(function (n) { var a = s.el.querySelector('[data-ve-btn="' + n + '"]'); if (a) a.setAttribute('data-ve-link', s['btn' + n + '_link']); });
            ghosts(s);
        }
        function bindPresident(doc) {
            Object.keys(PKEYS).forEach(function (k) {
                var el = doc.querySelector('[data-ve-field="' + k + '"]');
                if (el) makeField(el, function (e, val) { pres[PKEYS[k]] = k === 'pres-message' ? normMsg(val) : val; });
            });
            presImg = doc.querySelector('[data-ve-img="pres-photo"]');
            $$('[data-ve-field="home-text"]', doc).forEach(function (el) {
                var k = el.getAttribute('data-ve-key');
                if (HC[k]) makeField(el, function (e, val) { hc[k] = val; });
            });
        }

        // ── showing a changed background right away ──
        function setBg(layer, url) {
            layer.classList.remove('skeleton-hero', 'lazy-bg');
            layer.style.backgroundImage = 'url("' + String(url).replace(/"/g, '%22') + '")';
            layer.style.backgroundSize = 'cover'; layer.style.backgroundPosition = 'center'; layer.setAttribute('data-bg', url);
        }
        function paintBg(s) {
            var el = s.el, d = el.querySelector('[data-ve-bg="desktop"]'), m = el.querySelector('[data-ve-bg="mobile"]'), v = el.querySelector('[data-ve-bg="video"]');
            var dUrl = s.file ? s._url : s.media_path;
            if (s.media_type === 'video') {
                if (d) d.style.display = 'none'; if (m) m.style.display = 'none';
                if (!v) { v = S.doc.createElement('div'); v.className = 'slide-video-wrap'; v.setAttribute('data-ve-bg', 'video'); el.insertBefore(v, el.firstChild); }
                v.style.display = ''; v.innerHTML = '';
                var vid = S.doc.createElement('video'); vid.className = 'slide-video hero-slide-video'; vid.muted = true; vid.loop = true; vid.autoplay = true; vid.setAttribute('playsinline', ''); vid.src = dUrl; v.appendChild(vid);
                try { vid.play(); } catch (e) {}
            } else {
                if (v) v.style.display = 'none';
                var mUrl = s.removeMobile ? dUrl : (s.mfile ? s._murl : (s.media_path_mobile || dUrl));
                if (d) { d.style.display = ''; setBg(d, dUrl); }
                if (m) { m.style.display = ''; setBg(m, mUrl); }
            }
        }
        function paintFlags(s) {
            var ov = s.el.querySelector('[data-ve-overlay]'); if (ov) ov.style.display = s.show_gradient ? '' : 'none';
            s.el.setAttribute('data-ve-status', s.status);
            s.el.setAttribute('data-warning-active', s.status === 'published' ? 'true' : 'false');
        }

        // ── moving between slides ──
        function goTo(i) {
            var s = slides[i]; if (!s || !s.el) return; cur = i;
            var all = $$('.hero-slide', S.doc), n = all.indexOf(s.el);
            var dots = $$('.hero-dots span', S.doc);
            if (dots[n]) dots[n].click(); else all.forEach(function (e) { e.classList.toggle('active', e === s.el); });
            renderDock();
        }
        function syncCur() {
            var a = S.doc && S.doc.querySelector('.hero-slide.active'); if (!a) return;
            for (var i = 0; i < slides.length; i++) if (slides[i].el === a) { cur = i; break; }
            renderDock();
        }

        // ── the film strip under the page ──
        function renderDock() {
            dock.hidden = false; dock.innerHTML = '';
            dock.appendChild(h('div', { class: 've-dock-label' }, [icon('collection-play'), h('span', { text: 'Slides' })]));
            var track = h('div', { class: 've-dock-track', role: 'list' });
            slides.forEach(function (s, i) {
                var src = s.file ? s._url : s.media_path;
                var media = s.media_type === 'video' ? h('span', { class: 've-thumb-img ve-thumb-video' }, [icon('camera-video')]) : h('img', { class: 've-thumb-img', src: src, alt: '' });
                var b = h('button', { type: 'button', class: 've-thumb' + (i === cur ? ' is-active' : ''), draggable: 'true', role: 'listitem', 'data-i': i,
                    title: 'Slide ' + (i + 1) + ' · drag to change the order',
                    on: { click: function () { goTo(i); select(S.doc && s.el ? { kind: 'slide', el: s.el, slide: s, anchor: 'bottom', prevent: true } : null); } } }, [
                    media, h('span', { class: 've-thumb-n', text: String(i + 1) }),
                    h('span', { class: 've-thumb-t', text: clip(String(s.title || '').replace(/\|/g, ' ') || 'Untitled slide', 22) }),
                    s.status === 'draft' ? h('span', { class: 've-thumb-badge', text: 'Draft' }) : null,
                    slideDirty(s) ? h('span', { class: 've-thumb-dirty', title: 'Unsaved changes' }) : null]);
                b.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(i)); e.dataTransfer.effectAllowed = 'move'; b.classList.add('is-drag'); });
                b.addEventListener('dragend', function () { b.classList.remove('is-drag'); $$('.ve-thumb', dock).forEach(function (x) { x.classList.remove('is-over'); }); });
                b.addEventListener('dragover', function (e) { e.preventDefault(); b.classList.add('is-over'); });
                b.addEventListener('dragleave', function () { b.classList.remove('is-over'); });
                b.addEventListener('drop', function (e) { e.preventDefault(); var from = parseInt(e.dataTransfer.getData('text/plain'), 10); if (!isNaN(from)) move(from, i); });
                track.appendChild(b);
            });
            dock.appendChild(track);
            dock.appendChild(h('button', { type: 'button', class: 've-add-slide', on: { click: addSlide } }, [icon('plus-lg'), h('span', { text: 'Add slide' })]));
            emptyBox.hidden = slides.length > 0;
        }
        function move(from, to) {
            if (from === to || from < 0 || to < 0 || from >= slides.length || to >= slides.length) return;
            var curSlide = slides[cur]; var m = slides.splice(from, 1)[0]; slides.splice(to, 0, m);
            orderDirty = true; cur = Math.max(0, slides.indexOf(curSlide)); renderDock(); renderToolbar(); chrome();
        }

        // ── adding, deleting, refreshing ──
        function refresh(index, selectId) {
            return fetch(C.contentHandler + '?action=list', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res || !res.slides) { toast('Saved, but the slide list could not be refreshed. Reload the page.', 'error'); return; }
                loadSlides(res.slides);
                wantCur = selectId ? Math.max(0, slides.map(function (s) { return s.id; }).indexOf(+selectId)) : Math.max(0, Math.min(index == null ? cur : index, slides.length - 1));
                loadFrame(S.url, true);
            }).catch(function () { toast('Saved, but the preview could not be refreshed. Reload the page.', 'error'); });
        }
        async function addSlide() {
            if (!await guardDirty('Adding a slide reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            pickFile('image/*,video/*', function (f) {
                run('Adding slide…', function () {
                    return sendContent({ action: 'save', slide_id: 0, title: '', subtitle: '', btn1_text: '', btn1_link: '', btn2_text: '', btn2_link: '', status: 'draft', show_on_mobile: '1', show_gradient: '1' }, { media: f }).then(function (res) {
                        if (!res.ok) { toast(res.error, 'error'); return; }
                        toast('Slide added as a draft. Write on it, then press Publish when it is ready.', 'ok');
                        return refresh(null, res.slide_id);
                    });
                });
            });
        }
        async function deleteSlide(s) {
            if (!await guardDirty('Deleting a slide reloads the preview, so your other unsaved changes would be lost.\n\nContinue?')) return;
            if (!await window.AdminDialogs.confirm('Delete this slide for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
            run('Deleting…', function () {
                return sendContent({ action: 'delete', slide_id: s.id }).then(function (res) {
                    if (!res.ok) { toast(res.error, 'error'); return; }
                    toast('Slide deleted.', 'ok'); return refresh(Math.max(0, idx(s) - 1));
                });
            });
        }

        // ── heading icons (Bootstrap Icons class names, e.g. "bi-megaphone-fill") ──
        var ICON_CHOICES = ['megaphone-fill', 'newspaper', 'bell-fill', 'broadcast', 'chat-left-text-fill', 'info-circle-fill', 'bookmark-star-fill', 'star-fill',
            'calendar-event-fill', 'calendar-check-fill', 'calendar-date-fill', 'clock-fill', 'alarm-fill', 'flag-fill', 'trophy-fill', 'balloon-fill',
            'mortarboard-fill', 'book-fill', 'journal-bookmark-fill', 'pencil-fill', 'lightbulb-fill', 'award-fill', 'building', 'people-fill',
            'heart-fill', 'globe2', 'geo-alt-fill', 'envelope-fill', 'camera-fill', 'music-note-beamed', 'cup-hot-fill', 'tree-fill'];
        function setIcon(sel, cls) {
            var ic = sel.el, old = String(hc[sel.key] || '');
            if (old) ic.classList.remove(old);
            // also drop whatever bi-* glyph class the page currently shows, so the swap is clean
            Array.prototype.slice.call(ic.classList).forEach(function (c) { if (/^bi-/.test(c)) ic.classList.remove(c); });
            ic.classList.add(cls); hc[sel.key] = cls;
            renderToolbar(); chrome();
        }
        function iconPicker(sel) {
            var cur = String(hc[sel.key] || '');
            var grid = h('div', { class: 've-icon-grid', role: 'listbox', 'aria-label': 'Choose an icon' }, ICON_CHOICES.map(function (n) {
                return h('button', { type: 'button', class: 've-icon-opt' + (cur === 'bi-' + n ? ' is-on' : ''), title: n, 'aria-label': n,
                    on: { click: function () { closePop(); setIcon(sel, 'bi-' + n); } } }, [icon(n)]);
            }));
            var input = h('input', { class: 'form-control form-control-sm', type: 'text', placeholder: 'e.g. bi-megaphone-fill', maxlength: 60, value: cur });
            var go = function () {
                var v = input.value.trim().replace(/^(?!bi-)/, 'bi-');
                if (!/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/.test(v)) { toast('That is not a valid icon name. Use a Bootstrap Icons name such as bi-megaphone-fill.', 'error'); return; }
                closePop(); setIcon(sel, v);
            };
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
            popBelowToolbar(h('div', { class: 've-pop-form' }, [
                h('div', { class: 've-pop-label', text: 'Pick an icon' }), grid,
                h('label', { class: 've-pop-label', text: 'Or type any icon name' }), input,
                h('small', { class: 've-pop-hint', text: 'Browse names at icons.getbootstrap.com. Leave the “bi-” part off if you like.' }),
                h('div', { class: 've-pop-row' }, [h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Use this icon', on: { click: go } })])
            ]));
        }

        // ── toolbars ──
        function editLink(sel) {
            var s = sel.slide, n = sel.el.getAttribute('data-ve-field').slice(-1), a = s.el.querySelector('[data-ve-btn="' + n + '"]');
            askText({ label: 'Where does this button go?', value: s['btn' + n + '_link'], placeholder: '/auth/login.php   or   https://…', max: 255,
                hint: 'Pages on this website start with /. Leave empty for no link.',
                done: function (v) {
                    var ok = safeLink(v); if (ok === null) { toast('That kind of link is not allowed.', 'error'); return; }
                    s['btn' + n + '_link'] = ok; if (a) { a.setAttribute('data-ve-link', ok); a.setAttribute('href', ok || '#'); }
                    renderToolbar(); chrome(); renderDock();
                } });
        }
        function phoneMenu(s) {
            var has = !!(s.mfile || (s.media_path_mobile && !s.removeMobile));
            var thumb = s.mfile ? s._murl : (s.removeMobile ? '' : s.media_path_mobile);
            var box = h('div', { class: 've-pop-form' }, [
                h('div', { class: 've-pop-label', text: 'Picture shown on phones' }),
                thumb ? h('img', { class: 've-phone-thumb', src: thumb, alt: '' }) : h('small', { class: 've-pop-hint', text: 'Phones show the main picture, cropped to fit. Add a tall picture for a better result.' }),
                h('div', { class: 've-pop-row' }, [
                    h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: has ? 'Replace phone picture' : 'Upload phone picture', on: { click: function () {
                        closePop(); pickFile('image/*', function (f) { s.mfile = f; s._murl = previewUrl(f); s.removeMobile = false; paintBg(s); renderDock(); renderToolbar(); chrome(); }); } } }),
                    has ? h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Use main picture', on: { click: function () {
                        closePop(); s.mfile = null; s.removeMobile = !!s.media_path_mobile; paintBg(s); renderDock(); renderToolbar(); chrome(); } } }) : null
                ])]);
            popBelowToolbar(box);
        }
        function changeBg(s) {
            pickFile('image/*,video/*', function (f) {
                s.file = f; s._url = previewUrl(f); s.media_type = /^video\//.test(f.type) ? 'video' : 'image';
                paintBg(s); renderDock(); renderToolbar(); chrome();
            });
        }

        // ── saving ──
        function saveSlide(s) {
            return sendContent({
                action: 'save', slide_id: s.id, title: s.title, subtitle: s.subtitle, btn1_text: s.btn1_text, btn1_link: s.btn1_link,
                btn2_text: s.btn2_text, btn2_link: s.btn2_link, status: s.status, show_on_mobile: s.show_on_mobile ? '1' : '0',
                show_gradient: s.show_gradient ? '1' : '0', remove_mobile_media: s.removeMobile ? '1' : ''
            }, { media: s.file, media_mobile: s.mfile });
        }
        function savePresident() {
            var f = { action: 'save_homepage_president', remove_photo: presRemove ? '1' : '' };
            Object.keys(PKEYS).forEach(function (k) { f[PKEYS[k]] = pres[PKEYS[k]]; });
            return sendContent(f, { photo: presFile });
        }

        var A = {
            draftState: function (value) {
                if(!value)return {slides:slides,cur:cur,orderDirty:orderDirty,pres:pres,presOrig:presOrig,presFile:presFile,presRemove:presRemove,HC:HC,hc:hc,hcFiles:hcFiles,hcReset:hcReset};
                slides=value.slides;cur=value.cur;orderDirty=value.orderDirty;pres=value.pres;presOrig=value.presOrig;presFile=value.presFile;presRemove=value.presRemove;HC=value.HC;hc=value.hc;hcFiles=value.hcFiles;hcReset=value.hcReset;
                slides.forEach(function(slide){slide.el=S.doc.querySelector('[data-ve-slide="'+slide.id+'"]');if(slide.el){bindSlide(slide);paintBg(slide);paintFlags(slide);}});bindPresident(S.doc);renderDock();
            },
            draftRebind: function(){slides.forEach(bindSlide);bindPresident(S.doc);renderDock();},
            mount: function (doc) {
                var els = $$('[data-ve-slide]', doc);
                slides.forEach(function (s) { s.el = els.filter(function (e) { return +e.getAttribute('data-ve-slide') === s.id; })[0] || null; });
                if (slides.length && !els.length) toast('The homepage file does not carry the editor markers, so the slideshow cannot be edited in place. Please update index.php.', 'error');
                slides.forEach(bindSlide); bindPresident(doc);
                renderDock();
                return false;
            },
            afterMount: function () {
                var i = wantCur != null ? wantCur : cur; wantCur = null;
                if (slides[i] && slides[i].el) { goTo(i); }
                if (C.focusSection === 'president') { C.focusSection = null; var p = $('#president', S.doc); if (p) p.scrollIntoView({ block: 'start' }); }
            },
            pick: function (t) {
                var fe = fieldOf(t);
                if (fe && fe.getAttribute('data-ve-field') === 'home-text') {
                    var ha = fe.closest('[data-ve-linkkey]');
                    return ha ? { kind: 'hbutton', el: fe, key: ha.getAttribute('data-ve-linkkey'), a: ha, prevent: false } : { kind: 'field', el: fe, prevent: false };
                }
                if (fe) {
                    var f = fe.getAttribute('data-ve-field');
                    if (f.indexOf('slide-btn') === 0) return { kind: 'button', el: fe, slide: slideOfEl(fe), prevent: false };
                    if (f.indexOf('slide-') === 0) return { kind: 'field', el: fe, slide: slideOfEl(fe), prevent: false };
                    if (f.indexOf('pres-') === 0) return { kind: 'field', el: fe, pres: true, prevent: false };
                    return null;
                }
                var hb = t.closest && t.closest('[data-ve-linkkey]');
                if (hb) { var hf = hb.querySelector('[data-ve-field]'); if (hf) { hf.focus(); return { kind: 'hbutton', el: hf, key: hb.getAttribute('data-ve-linkkey'), a: hb, prevent: true }; } }
                var hi = t.closest && t.closest('[data-ve-img="home-image"]');
                if (hi) return { kind: 'himage', el: hi, key: hi.getAttribute('data-ve-key'), prevent: true };
                var hbg = t.closest && t.closest('[data-ve-img="home-background"]');
                if (hbg) return { kind: 'himage', el: hbg, area: hbg.closest('section'), key: hbg.getAttribute('data-ve-key'), prevent: true };
                var hic = t.closest && t.closest('[data-ve-icon="home-icon"]');
                if (hic && HC[hic.getAttribute('data-ve-key')]) return { kind: 'hicon', el: hic, key: hic.getAttribute('data-ve-key'), prevent: true };
                var b = t.closest && t.closest('[data-ve-btn]');
                if (b) { var inner = b.querySelector('[data-ve-field]'); if (inner) { inner.focus(); return { kind: 'button', el: inner, slide: slideOfEl(inner), prevent: true }; } }
                var im = t.closest && t.closest('[data-ve-img="pres-photo"]');
                if (im) return { kind: 'image', el: im, pres: true, prevent: true };
                if (t.closest && t.closest('.hero-dots,.slide-arrow')) { window.setTimeout(syncCur, 120); return null; }
                var se = t.closest && t.closest('[data-ve-slide]');
                if (se) { var s = slideOfEl(se); if (s) { syncCur(); return { kind: 'slide', el: se, slide: s, anchor: 'bottom', prevent: true }; } }
                var pp = postPick(t); if (pp) return pp;
                return null;
            },
            hoverFallback: function (t) { return t.closest && !t.closest('.hero-dots,.slide-arrow') ? t.closest('[data-ve-slide]') : null; },
            toolbar: function (sel) {
                if (sel.kind === 'post') return postToolbar(sel);
                var it = [];
                if (sel.kind === 'field') {
                    var v = sel.el.__ve || {}, len = readField(sel.el).length;
                    it.push({ type: 'chip', icon: 'pencil', text: (v.label || 'Text') + (v.max ? '  ·  ' + len + ' / ' + v.max : '') });
                } else if (sel.kind === 'button') {
                    var s = sel.slide, n = sel.el.getAttribute('data-ve-field').slice(-1);
                    it.push({ type: 'chip', icon: 'cursor', text: 'Button  ·  ' + readField(sel.el).length + ' / 30' });
                    it.push({ icon: 'link-45deg', label: clip(s['btn' + n + '_link'] || 'Add a link', 26), title: 'Where the button goes', run: function () { editLink(sel); } });
                    it.push({ icon: 'trash', title: 'Remove this button', danger: true, run: function () { sel.el.textContent = ''; markEmpty(sel.el); readSlide(s); s['btn' + n + '_link'] = ''; renderToolbar(); chrome(); renderDock(); } });
                } else if (sel.kind === 'hbutton') {
                    var lk = sel.key, v2 = sel.el.__ve || {};
                    it.push({ type: 'chip', icon: 'cursor', text: 'Button  ·  ' + readField(sel.el).length + ' / ' + (v2.max || 30) });
                    it.push({ icon: 'link-45deg', label: clip(hc[lk] || 'Add a link', 30), title: 'Where the button goes', run: function () {
                        askText({ label: 'Where does this button go?', value: hc[lk], placeholder: '/pages/…   or   https://…', max: 255, hint: 'Pages on this website start with /. Leave empty to use the original link.',
                            done: function (v) { var ok = safeLink(v); if (ok === null) { toast('That kind of link is not allowed.', 'error'); return; }
                                hc[lk] = ok; sel.a.setAttribute('data-ve-link', ok); sel.a.setAttribute('href', ok || '#'); renderToolbar(); chrome(); } });
                    } });
                } else if (sel.kind === 'hicon') {
                    var ik = sel.key;
                    it.push({ type: 'chip', icon: 'emoji-smile', text: (HC[ik] && HC[ik].label) || 'Icon' });
                    it.push({ icon: 'grid-3x3-gap', label: 'Change icon', primary: true, run: function () { iconPicker(sel); } });
                    if (hc[ik] !== HC[ik].default) it.push({ icon: 'arrow-counterclockwise', label: 'Use original', title: 'Go back to the original icon', run: function () { setIcon(sel, HC[ik].default); } });
                    if (hc[ik] !== HC[ik].value) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Not saved yet' });
                } else if (sel.kind === 'himage') {
                    var hk = sel.key, changed = !!hcFiles[hk];
                    it.push({ type: 'chip', icon: 'image', text: (HC[hk] && HC[hk].label) || 'Picture' });
                    it.push({ icon: 'image', label: sel.area ? 'Change background image' : 'Replace picture', primary: true, run: function () {
                        pickFile('image/*', function (f) { hcFiles[hk] = f; delete hcReset[hk]; paintHomeImage(sel, previewUrl(f)); renderToolbar(); chrome(); }, false, sel.area || sel.el, !!sel.area); } });
                    if (changed || (HC[hk] && HC[hk].value !== HC[hk].default && !hcReset[hk])) it.push({ icon: 'arrow-counterclockwise', label: 'Use original', title: 'Go back to the original picture', run: function () {
                        delete hcFiles[hk];
                        if (HC[hk].value === HC[hk].default) delete hcReset[hk]; else hcReset[hk] = true;
                        paintHomeImage(sel, '/' + HC[hk].default.replace(/^\/+/, '')); renderToolbar(); chrome(); } });
                    if (changed) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'New picture, not saved yet' });
                    if (hcReset[hk]) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Will go back to the original' });
                } else if (sel.kind === 'image') {
                    var has = !!(presImg && presImg.getAttribute('src')) && !presRemove;
                    it.push({ icon: 'image', label: 'Replace photo', primary: true, run: function () {
                        pickPersonPhoto(function (f) { presFile = f; presRemove = false; presImg.src = previewUrl(f); presImg.style.opacity = ''; renderToolbar(); chrome(); }); } });
                    if (has) it.push({ icon: 'trash', title: 'Remove the photo', danger: true, run: function () { presRemove = true; presFile = null; presImg.style.opacity = '.25'; renderToolbar(); chrome(); } });
                    if (presFile) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'New photo, not saved yet' });
                    if (presRemove) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Photo will be removed' });
                } else if (sel.kind === 'slide') {
                    var sl = sel.slide, i = idx(sl);
                    it.push({ type: 'chip', icon: 'collection-play', text: 'Slide ' + (i + 1) + ' of ' + slides.length });
                    it.push({ icon: 'image', label: 'Background', title: 'Change the picture or video behind the text', primary: true, run: function () { changeBg(sl); } });
                    it.push({ icon: 'phone', label: 'Phone picture', title: 'A separate picture for phones', run: function () { phoneMenu(sl); } });
                    it.push({ type: 'sep' });
                    it.push({ icon: 'circle-half', label: 'Shade', title: 'Dark shade behind the text so it is easy to read', on: !!sl.show_gradient, run: function () { sl.show_gradient = sl.show_gradient ? 0 : 1; paintFlags(sl); renderToolbar(); chrome(); } });
                    it.push({ icon: sl.show_on_mobile ? 'check2-square' : 'square', label: 'On phones', title: 'Show this slide on phones', on: !!sl.show_on_mobile, run: function () { sl.show_on_mobile = sl.show_on_mobile ? 0 : 1; renderToolbar(); chrome(); } });
                    it.push({ icon: sl.status === 'published' ? 'eye' : 'eye-slash', label: sl.status === 'published' ? 'Published' : 'Draft', title: sl.status === 'published' ? 'Visitors can see this slide. Click to hide it.' : 'Hidden from visitors. Click to publish it.', on: sl.status === 'published',
                        run: function () { sl.status = sl.status === 'published' ? 'draft' : 'published'; paintFlags(sl); renderToolbar(); chrome(); renderDock(); } });
                    it.push({ type: 'sep' });
                    it.push({ icon: 'arrow-left', title: 'Move this slide earlier', disabled: i === 0, run: function () { move(i, i - 1); } });
                    it.push({ icon: 'arrow-right', title: 'Move this slide later', disabled: i === slides.length - 1, run: function () { move(i, i + 1); } });
                    it.push({ icon: 'trash', title: 'Delete this slide', danger: true, run: function () { deleteSlide(sl); } });
                }
                return it;
            },
            dirtyCount: function () { return slides.filter(slideDirty).length + (presDirty() ? 1 : 0) + (orderDirty ? 1 : 0) + hcCount(); },
            feedbackTargets: function () { return slides.filter(function(s){return orderDirty || slideDirty(s);}).map(function(s){return s.el;}).filter(Boolean); },
            status: function () {
                var n = A.dirtyCount();
                if (n) return { text: plural(n, 'unsaved change'), tone: 'warn' };
                var d = slides.filter(function (s) { return s.status === 'draft'; }).length;
                return { text: d ? plural(d, 'draft slide') + ' hidden from visitors' : 'Everything here is live', tone: d ? 'warn' : 'ok' };
            },
            actions: function () {
                return { secondary: null, primary: { label: 'Publish', icon: 'cloud-upload', disabled: A.dirtyCount() === 0,
                    title: 'Slides and the president message go live as soon as you publish. To keep a slide private, set it to Draft first.' } };
            },
            runPrimary: function () {
                var list = slides.filter(slideDirty), doPres = presDirty(), doOrder = orderDirty, doHome = hcCount() > 0;
                if (!list.length && !doPres && !doOrder && !doHome) return;
                var published = list.filter(function (s) { return s.status === 'published'; }).length;
                confirmPublish('Publish your changes to the live website?' + (published ? '\n\n' + plural(published, 'published slide') + ' will change for visitors.' : ''), function () {
                    run('Publishing…', function () {
                        var errs = [], done = 0, p = Promise.resolve();
                        list.forEach(function (s) { p = p.then(function () { return saveSlide(s).then(function (r) { if (r.ok) done++; else errs.push('Slide ' + (idx(s) + 1) + ': ' + r.error); }); }); });
                        if (doOrder) p = p.then(function () { return sendContent({ action: 'reorder', order: JSON.stringify(slides.map(function (s) { return s.id; })) }).then(function (r) { if (r.ok) done++; else errs.push('Order: ' + r.error); }); });
                        if (doPres) p = p.then(function () { return savePresident().then(function (r) { if (r.ok) { done++; loadPresident(r.president); } else errs.push('President message: ' + r.error); }); });
                        if (doHome) p = p.then(function () {
                            var texts = {}; hcTextKeys().forEach(function (k) { texts[k] = hc[k]; });
                            var files = {}; Object.keys(hcFiles).forEach(function (k) { files['img_' + k] = hcFiles[k]; });
                            var resets = Object.keys(hcReset).filter(function (k) { return !hcFiles[k]; });
                            return sendContent({ action: 'save_home_content', texts: JSON.stringify(texts), reset_images: JSON.stringify(resets) }, files).then(function (r) {
                                if (r.ok) { done++; loadContent(r.content); } else errs.push('Homepage text: ' + r.error); });
                        });
                        return p.then(function () {
                            errs.forEach(function (m) { toast(m, 'error'); });
                            if (done && !errs.length) toast(published || doHome || doPres || doOrder ? 'Published. Your changes are live now.' : 'Draft saved. Your changes are not live yet.', 'ok');
                            if (done && !errs.length) return refresh(cur);       // reload from the server: proves what was saved
                            if (done) return refresh(cur);
                        });
                    });
                });
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: Administration (people and offices) ══════════════════
    function AdminAdapter() {
        var secs = (C.admin && C.admin.sections) || [];
        var curKey = C.page.section || (secs[0] && secs[0].key) || '';
        var edits = { sec: {}, person: {}, office: {}, order: {} };
        var suppressClickUntil=0, initialOrders=new WeakMap();
        D.addEventListener('administration:updated',function(event){
            if(event.detail&&event.detail.source==='settings'&&Array.isArray(event.detail.sections))secs=event.detail.sections;
        });
        function syncSettings(sections){
            secs=sections;
            D.dispatchEvent(new CustomEvent('administration:updated',{detail:{source:'canvas',sections:sections,sectionKey:curKey}}));
        }

        function secBy(k) { for (var i = 0; i < secs.length; i++) if (secs[i].key === k) return secs[i]; return null; }
        function personBy(id) { for (var i = 0; i < secs.length; i++) for (var j = 0; j < secs[i].people.length; j++) if (secs[i].people[j].id === id) return { p: secs[i].people[j], sec: secs[i] }; return null; }
        function officeBy(id) { for (var i = 0; i < secs.length; i++) for (var j = 0; j < secs[i].offices.length; j++) if (secs[i].offices[j].id === id) return { o: secs[i].offices[j], sec: secs[i] }; return null; }
        function setSec(key, f, v) {
            var s = secBy(key); if (!s) return; var e = edits.sec[key] || (edits.sec[key] = {}); e[f] = v;
            if ((e.title == null || e.title === s.title) && (e.desc == null || e.desc === s.desc)) delete edits.sec[key];
        }
        function setPerson(id, f, v) {
            var r = personBy(id); if (!r) return; var e = edits.person[id] || (edits.person[id] = {}); e[f] = v;
            if ((e.name == null || e.name === r.p.name) && (e.role == null || e.role === r.p.role) && !e.file && !e.remove) delete edits.person[id];
        }
        function cleanEdit(map, id) { var e = map[id]; if (e && !e.file && !e.remove && Object.keys(e).filter(function (k) { return k !== 'file' && k !== 'remove' && e[k] != null; }).length === 0) delete map[id]; }
        function unitOf(el) { var g = el.closest('[data-ve-grid]'); var u = el; while (u && u.parentNode !== g) u = u.parentNode; return u; }
        function idsInGrid(grid, attr) { return $$('[' + attr + ']', grid).map(function (e) { return +e.getAttribute(attr); }); }
        function rememberOrder(grid,attr) {
            var key=grid.getAttribute('data-ve-key')||curKey,section=secBy(key),ids=idsInGrid(grid,attr);
            var baseline=initialOrders.get(grid)||(section?(attr==='data-ve-office'?section.offices:section.people).map(function(item){return +item.id;}):[]);
            if(same(ids,baseline))delete edits.order[key];else edits.order[key]=ids;
            renderToolbar();chrome();
        }
        function bindDrag(grid,doc) {
            var selector=grid.getAttribute('data-ve-grid')==='offices'?'[data-ve-office]':'[data-ve-person]';
            var attr=selector.slice(1,-1),pending=null,timer=null,drag=null,target=null,preview=null,liftFrame=null;
            initialOrders.set(grid,idsInGrid(grid,attr));
            $$(selector,grid).forEach(function(card){
                card.style.position='relative';
                card.appendChild(h('button',{type:'button',class:'ve-drag-handle','aria-label':'Hold and drag to swap positions',title:'Hold and drag onto another card to swap positions'},[icon('grip-vertical'),h('span',{text:'Move'})]));
            });
            function follow(x,y){
                if(!preview||!drag)return;
                preview.style.transform='translate3d('+(x-drag.offsetX)+'px,'+(y-drag.offsetY)+'px,0)';
            }
            function showPreview(){
                var rect=drag.card.getBoundingClientRect(),clone=drag.card.cloneNode(true);
                drag.offsetX=drag.x-rect.left;drag.offsetY=drag.y-rect.top;
                [clone].concat($$('*',clone)).forEach(function(el){
                    Array.prototype.slice.call(el.attributes).forEach(function(attribute){if(attribute.name==='id'||attribute.name==='contenteditable'||attribute.name.indexOf('data-ve-')===0)el.removeAttribute(attribute.name);});
                    Array.prototype.slice.call(el.classList).forEach(function(name){if(name.indexOf('reveal')!==-1||name==='ve-hover'||name==='ve-selected')el.classList.remove(name);});
                });
                clone.style.width='100%';clone.style.height='100%';clone.style.margin='0';clone.style.animation='none';clone.style.transitionDelay='0s';
                var inner=doc.createElement('div');inner.style.width='100%';inner.style.height='100%';inner.appendChild(clone);
                preview=doc.createElement('div');preview.className='ve-drag-preview';preview.setAttribute('aria-hidden','true');preview.setAttribute('inert','');
                preview.style.width=rect.width+'px';preview.style.height=rect.height+'px';preview.appendChild(inner);doc.body.appendChild(preview);follow(drag.x,drag.y);
                liftFrame=doc.defaultView.requestAnimationFrame(function(){if(preview)preview.classList.add('is-lifted');});
            }
            function clear(landing){
                clearTimeout(timer);timer=null;
                if(liftFrame)doc.defaultView.cancelAnimationFrame(liftFrame);liftFrame=null;
                if(drag){drag.card.classList.remove('ve-drag-source');try{drag.card.releasePointerCapture(drag.pointerId);}catch(_){}suppressClickUntil=Date.now()+400;}
                if(target)target.classList.remove('ve-drag-target');
                if(preview){
                    var floating=preview;preview=null;
                    if(landing&&landing.nodeType===1&&!doc.defaultView.matchMedia('(prefers-reduced-motion: reduce)').matches){
                        var rect=landing.getBoundingClientRect();
                        floating.animate([{transform:floating.style.transform,opacity:1},{transform:'translate3d('+rect.left+'px,'+rect.top+'px,0)',opacity:0}],{duration:180,easing:'ease-out'}).finished.then(function(){floating.remove();},function(){floating.remove();});
                    }else floating.remove();
                }
                doc.documentElement.classList.remove('ve-dragging');pending=null;drag=null;target=null;
            }
            grid.addEventListener('pointerdown',function(event){
                if(S.preview||S.busy||!event.isPrimary||event.button!==0)return;
                var card=event.target.closest(selector);if(!card||card.closest('[data-ve-grid]')!==grid)return;
                var handle=event.target.closest('.ve-drag-handle');
                if(!handle&&event.target.closest('[contenteditable],button,a,input,textarea,select'))return;
                if(event.pointerType==='touch'&&!handle)return;
                pending={card:card,x:event.clientX,y:event.clientY,pointerId:event.pointerId};
                timer=setTimeout(function(){
                    if(!pending||S.preview||S.busy)return;
                    drag=pending;pending=null;select(null);closePop();doc.defaultView.getSelection().removeAllRanges();
                    showPreview();drag.card.classList.add('ve-drag-source');doc.documentElement.classList.add('ve-dragging');
                    try{drag.card.setPointerCapture(drag.pointerId);}catch(_){}
                },350);
            });
            doc.addEventListener('pointermove',function(event){
                if(pending&&event.pointerId===pending.pointerId&&Math.hypot(event.clientX-pending.x,event.clientY-pending.y)>8){clear();return;}
                if(!drag||event.pointerId!==drag.pointerId)return;
                if(S.preview||S.busy){clear();return;}
                event.preventDefault();
                follow(event.clientX,event.clientY);
                var under=doc.elementFromPoint(event.clientX,event.clientY),next=under&&under.closest(selector);
                if(!next||next===drag.card||next.closest('[data-ve-grid]')!==grid)next=null;
                if(target!==next){if(target)target.classList.remove('ve-drag-target');target=next;if(target)target.classList.add('ve-drag-target');}
                var view=doc.defaultView;if(event.clientY<50)view.scrollBy(0,-18);else if(event.clientY>view.innerHeight-50)view.scrollBy(0,18);
            },{passive:false});
            doc.addEventListener('pointerup',function(event){
                if(drag&&event.pointerId!==drag.pointerId)return;
                var landing=null;
                if(drag&&target&&!S.preview&&!S.busy){
                    event.preventDefault();var first=unitOf(drag.card),second=unitOf(target),marker=doc.createComment('swap');
                    grid.insertBefore(marker,first);grid.insertBefore(first,second);grid.insertBefore(second,marker);marker.remove();
                    landing=drag.card;
                    rememberOrder(grid,attr);toast('Positions swapped. Publish to save the new order.','ok');
                }
                clear(landing);
            });
            doc.addEventListener('pointercancel',clear);
            doc.addEventListener('keydown',function(event){if(event.key==='Escape')clear();});
            doc.defaultView.addEventListener('blur',clear);
        }

        function bind(doc) {
            $$('[data-ve-field]', doc).forEach(function (el) {
                var f = el.getAttribute('data-ve-field'), key = el.getAttribute('data-ve-key') || curKey, id = +el.getAttribute('data-ve-id');
                if (f === 'admin-section-title') makeField(el, function (e, v) { setSec(key, 'title', v); });
                else if (f === 'admin-section-desc') makeField(el, function (e, v) { setSec(key, 'desc', v); });
                else if (f === 'person-name') makeField(el, function (e, v) { setPerson(id, 'name', v); });
                else if (f === 'person-role') makeField(el, function (e, v) { setPerson(id, 'role', v); });
            });
            var cs = secBy(curKey);
            // "add" tiles at the end of each grid
            $$('[data-ve-grid]', doc).forEach(function (grid) {
                var offices = grid.getAttribute('data-ve-grid') === 'offices';
                var tile = doc.createElement('div'); tile.className = 've-add-tile'; tile.setAttribute('data-ve-add', offices ? 'office' : 'person');
                tile.innerHTML = '<span style="font-size:28px;line-height:1">+</span><span>' + (offices ? 'Add an office' : 'Add a person') + '</span>';
                grid.appendChild(tile);
                bindDrag(grid,doc);
            });
            if (cs && !$$('[data-ve-grid]', doc).length) toast('This tab has nothing to edit in place yet. Use “More settings” for the full list.', 'ok');
        }

        // ── forms in the popover ──
        function formField(label, input) { return h('label', { class: 've-fld' }, [h('span', { text: label }), input]); }
        function officeForm(sec, o, done) {
            var c = (o && o.contact) || {};
            var f = {
                office: h('input', { class: 'form-control form-control-sm', maxlength: 255, value: o ? o.office : '' }),
                head: h('input', { class: 'form-control form-control-sm', maxlength: 255, value: o ? o.name : '' }),
                role: h('input', { class: 'form-control form-control-sm', maxlength: 500, value: o ? o.role : '' }),
                phone: h('input', { class: 'form-control form-control-sm', maxlength: 100, value: c.phone || '' }),
                email: h('input', { class: 'form-control form-control-sm', maxlength: 255, value: c.email || '' }),
                loc: h('input', { class: 'form-control form-control-sm', maxlength: 255, value: c.location || '' }),
                hours: h('textarea', { class: 'form-control form-control-sm', rows: 2 }),
                svc: h('textarea', { class: 'form-control form-control-sm', rows: 3 })
            };
            f.hours.value = c.hours || ''; f.svc.value = o ? (o.services || []).join('\n') : '';
            var photo = null, pname = h('small', { class: 've-pop-hint', text: o && o.image ? 'Keeps the current photo unless you choose a new one.' : '' });
            var go = h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: o ? 'Apply changes' : 'Add office', on: { click: function () {
                if (!f.office.value.trim() || !f.head.value.trim()) { toast('Please fill in the office name and the head’s name.', 'error'); return; }
                closePop(); done({ office_name: f.office.value, head_name: f.head.value, head_role: f.role.value, phone: f.phone.value, email: f.email.value, location: f.loc.value, hours: f.hours.value, services: f.svc.value }, photo);
            } } });
            return h('div', { class: 've-pop-form ve-grid2' }, [
                h('div', { class: 've-pop-label ve-span2', text: o ? 'Edit office details' : 'Add an office' }),
                formField('Office name', f.office), formField('Head of office', f.head), formField('Position', f.role), formField('Phone', f.phone),
                formField('Email', f.email), formField('Location', f.loc), formField('Operating hours (one per line)', f.hours), formField('Services (one per line)', f.svc),
                h('div', { class: 've-span2 ve-pop-row' }, [
                    h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Choose photo', on: { click: function () { pickPersonPhoto(function (file) { photo = file; pname.textContent = 'New photo: ' + file.name; }); } } }), go]),
                h('div', { class: 've-span2' }, [pname])]);
        }
        function personForm(done) {
            var name = h('input', { class: 'form-control form-control-sm', maxlength: 255, placeholder: 'Full name' });
            var role = h('input', { class: 'form-control form-control-sm', maxlength: 500, placeholder: 'Position (optional)' });
            var photo = null, pn = h('small', { class: 've-pop-hint', text: 'A photo is optional. You can add one later.' });
            return h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'Add a person' }), formField('Name', name), formField('Position', role),
                h('div', { class: 've-pop-row' }, [
                    h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Choose photo', on: { click: function () { pickPersonPhoto(function (f) { photo = f; pn.textContent = 'Photo: ' + f.name; }); } } }),
                    h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Add to page', on: { click: function () {
                        if (!name.value.trim()) { name.focus(); return; } closePop(); done({ name: name.value, role: role.value }, photo); } } })]), pn]);
        }
        function take(res, msg) {
            if (!res.ok) { toast(res.error, 'error'); return false; }
            if (res.sections) syncSettings(res.sections); toast(msg || res.message || 'Saved.', 'ok'); return true;
        }
        function reload() { loadFrame(S.url, true); }

        // ── structural actions (add / archive / delete): done on purpose, so they apply straight away ──
        async function addPerson(key, pos, anchorX, anchorY) {
            if (!await guardDirty('Adding a person reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            openPop(personForm(function (v, photo) {
                run('Adding…', function () { return sendContent({ action: 'admin_save_person', person_id: 0, section_key: key, name: v.name, role: v.role }, { photo: photo }).then(function (r) { if (take(r, 'Added. They are on the page now.')) reload(); }); });
            }), anchorX, anchorY);
        }
        async function addOffice(key, anchorX, anchorY) {
            if (!await guardDirty('Adding an office reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            openPop(officeForm(secBy(key), null, function (v, photo) {
                run('Adding…', function () { v.action = 'admin_save_office'; v.office_id = 0; v.section_key = key; return sendContent(v, { photo: photo }).then(function (r) { if (take(r, 'Added. The office is on the page now.')) reload(); }); });
            }), anchorX, anchorY, null, true);
        }
        async function archiveItem(kind, id, name) {
            if (!await guardDirty('This reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            if (!await window.AdminDialogs.confirm('Hide “' + name + '” from the website?\n\nThey are kept safely. You can bring them back from “More settings” → Archived.')) return;
            run('Archiving…', function () { var f = { action: kind === 'office' ? 'admin_archive_office' : 'admin_archive_person', archived: '1' }; f[kind === 'office' ? 'office_id' : 'person_id'] = id;
                return sendContent(f).then(function (r) { if (take(r, 'Hidden from the website.')) reload(); }); });
        }
        async function deleteItem(kind, id, name) {
            if (!await guardDirty('This reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            if (!await window.AdminDialogs.confirm('Delete “' + name + '” for good?\n\nThis removes them from the website now and cannot be undone. (Use Archive to keep them.)')) return;
            run('Deleting…', function () { var f = { action: kind === 'office' ? 'admin_delete_office' : 'admin_delete_person' }; f[kind === 'office' ? 'office_id' : 'person_id'] = id;
                return sendContent(f).then(function (r) { if (take(r, 'Deleted.')) reload(); }); });
        }
        function shift(sel, dir) {
            var u = unitOf(sel.el), g = u.parentNode, sibs = Array.prototype.filter.call(g.children, function (c) { return c.getAttribute('data-ve-add') === null; });
            var i = sibs.indexOf(u), j = i + dir; if (j < 0 || j >= sibs.length) return;
            if (dir < 0) g.insertBefore(u, sibs[j]); else g.insertBefore(u, sibs[j].nextSibling);
            rememberOrder(g, sel.kind === 'office' ? 'data-ve-office' : 'data-ve-person');
        }
        function photoOf(sel) {
            return { get: function () { return sel.kind === 'office' ? edits.office[sel.id] : edits.person[sel.id]; },
                     map: sel.kind === 'office' ? edits.office : edits.person };
        }
        function replacePhoto(sel) {
            var img = sel.el.querySelector(sel.kind === 'office' ? '[data-ve-img="office-photo"]' : '[data-ve-img="person-photo"]');
            pickPersonPhoto(function (f) {
                var m = photoOf(sel).map; var e = m[sel.id] || (m[sel.id] = {}); e.file = f; e.remove = false;
                if (img) { img.src = previewUrl(f); img.style.opacity = ''; }
                renderToolbar(); chrome();
            }, img);
        }
        function removePhoto(sel) {
            var m = photoOf(sel).map; var e = m[sel.id] || (m[sel.id] = {}); e.file = null; e.remove = true;
            var img = sel.el.querySelector(sel.kind === 'office' ? '[data-ve-img="office-photo"]' : '[data-ve-img="person-photo"]'); if (img) img.style.opacity = '.2';
            renderToolbar(); chrome();
        }
        function editOffice(sel) {
            var r = officeBy(sel.id); if (!r) return;
            var tbr = tb.getBoundingClientRect();
            var pending = edits.office[sel.id] || {}, details = pending.details;
            var office = details ? Object.assign({}, r.o, { office: details.office_name, name: details.head_name, role: details.head_role,
                contact: { phone: details.phone, email: details.email, location: details.location, hours: details.hours }, services: details.services.split('\n') }) : r.o;
            openPop(officeForm(r.sec, office, function (v, photo) {
                var e = edits.office[sel.id] || (edits.office[sel.id] = {});
                e.details = v;
                if (photo) {
                    e.file = photo; e.remove = false;
                    var img = sel.el.querySelector('[data-ve-img="office-photo"]');
                    if (img) { img.src = previewUrl(photo); img.style.opacity = ''; }
                }
                paintOfficeDetails(sel, v);
                renderToolbar(); chrome();
                toast('Office details updated. Publish to save your changes.', 'ok');
            }), tbr.left, tbr.bottom + 8, null, true);
        }

        function paintOfficeDetails(sel, details) {
            var doc = sel.el.ownerDocument;
            function text(selector, value) { $$(selector, sel.el).forEach(function (el) { el.textContent = value; }); }
            text('.hss-office-title', details.office_name);
            text('.hss-badge-title', details.head_name);
            text('.hss-badge-sub', details.head_role);
            sel.el.setAttribute('data-ve-label', details.office_name);
            var contact = sel.el.querySelector('.hss-contact-list');
            if (contact) {
                contact.replaceChildren();
                [['phone', 'telephone', 'Contact Information'], ['email', 'envelope', 'Email Address'], ['location', 'geo-alt', 'Business Office'], ['hours', 'clock', 'Operating Hours']].forEach(function (field) {
                    if (!details[field[0]]) return;
                    var li = doc.createElement('li'), icon = doc.createElement('i'), body = doc.createElement('div'), label = doc.createElement('span'), value = doc.createElement('span');
                    icon.className = 'bi bi-' + field[1]; icon.setAttribute('aria-hidden', 'true');
                    label.className = 'hss-contact-label'; label.textContent = field[2];
                    value.className = 'hss-contact-value'; value.textContent = details[field[0]]; value.style.whiteSpace = 'pre-line';
                    body.append(label, value); li.append(icon, body); contact.append(li);
                });
            }
            var services = sel.el.querySelector('.hss-services-list');
            if (services) {
                services.replaceChildren();
                details.services.split('\n').filter(function (line) { return line.trim(); }).forEach(function (line) {
                    var li = doc.createElement('li'), icon = doc.createElement('i'); icon.className = 'bi bi-chevron-right'; icon.setAttribute('aria-hidden', 'true');
                    li.append(icon, doc.createTextNode(' ' + line)); services.append(li);
                });
            }
        }

        var A = {
            consumeClick: function(){return Date.now()<suppressClickUntil;},
            draftState: function(value){if(!value)return {edits:edits,curKey:curKey};edits=value.edits;curKey=value.curKey;
                Object.keys(edits.office).forEach(function(id){var el=S.doc.querySelector('[data-ve-office="'+cssEsc(id)+'"]');if(el&&edits.office[id].details)paintOfficeDetails({el:el,id:+id},edits.office[id].details);});
            },
            mount: function (doc) { bind(doc); return false; },
            onLink: async function (a) {
                var href = a.getAttribute('href') || '', m = /[?&]section=([^&#]+)/.exec(href);
                if (!m) return false;
                if (!await guardDirty('Switching tabs reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return true;
                curKey = decodeURIComponent(m[1]); loadFrame(withParam(S.url, 'section', curKey)); return true;
            },
            pick: function (t) {
                var add = t.closest && t.closest('[data-ve-add]');
                if (add) {
                    var r = add.getBoundingClientRect(), fr = S.frame.getBoundingClientRect(), key = add.parentNode.getAttribute('data-ve-key') || curKey;
                    var x = fr.left + r.left, y = fr.top + Math.min(r.bottom, fr.height) + 8;
                    if (add.getAttribute('data-ve-add') === 'office') addOffice(key, x, y); else addPerson(key, null, x, y);
                    return { kind: 'none', el: add, prevent: true };
                }
                var fe = fieldOf(t);
                if (fe) return { kind: 'field', el: fe, prevent: false };
                var img = t.closest && t.closest('[data-ve-img]');
                var card = t.closest && t.closest('[data-ve-person],[data-ve-office]');
                if (card) {
                    var isOff = card.hasAttribute('data-ve-office');
                    return { kind: isOff ? 'office' : 'person', el: card, id: +card.getAttribute(isOff ? 'data-ve-office' : 'data-ve-person'), prevent: true, onImg: !!img };
                }
                return null;
            },
            hoverFallback: function () { return null; },
            toolbar: function (sel) {
                var it = [];
                if (sel.kind === 'field') {
                    var v = sel.el.__ve || {}, len = readField(sel.el).length;
                    it.push({ type: 'chip', icon: 'pencil', text: (v.label || 'Text') + (v.max ? '  ·  ' + len + ' / ' + v.max : '') });
                } else if (sel.kind === 'person' || sel.kind === 'office') {
                    var off = sel.kind === 'office', r = off ? officeBy(sel.id) : personBy(sel.id); if (!r) return it;
                    var item = off ? r.o : r.p, nm = off ? item.office : item.name, e = photoOf(sel).get() || {};
                    it.push({ type: 'chip', icon: off ? 'building' : 'person', text: clip(nm, 28) });
                    if (off) it.push({ icon: 'pencil-square', label: 'Edit details', primary: true, run: function () { editOffice(sel); } });
                    it.push({ icon: 'image', label: 'Photo', title: 'Replace the photo', primary: !off, run: function () { replacePhoto(sel); } });
                    if ((item.image && !e.remove) || e.file) it.push({ icon: 'image-alt', title: 'Remove the photo', run: function () { removePhoto(sel); } });
                    if (e.file) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'New photo, not saved yet' });
                    if (e.remove) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Photo will be removed' });
                    it.push({ type: 'sep' });
                    it.push({ icon: 'arrow-left', title: 'Move earlier', run: function () { shift(sel, -1); } });
                    it.push({ icon: 'arrow-right', title: 'Move later', run: function () { shift(sel, 1); } });
                    it.push({ type: 'sep' });
                    it.push({ icon: 'archive', title: 'Hide from the website (keeps it)', run: function () { archiveItem(off ? 'office' : 'person', sel.id, nm); } });
                    it.push({ icon: 'trash', title: 'Delete for good', danger: true, run: function () { deleteItem(off ? 'office' : 'person', sel.id, nm); } });
                }
                return it;
            },
            dirtyCount: function () {
                return Object.keys(edits.sec).length + Object.keys(edits.person).length + Object.keys(edits.office).length + Object.keys(edits.order).length;
            },
            feedbackTargets: function () {
                return $$('[data-ve-person],[data-ve-office]',S.doc).filter(function(el){
                    return el.hasAttribute('data-ve-person') ? !!edits.person[el.getAttribute('data-ve-person')] : !!edits.office[el.getAttribute('data-ve-office')];
                });
            },
            status: function () { var n = A.dirtyCount(); return n ? { text: plural(n, 'unsaved change'), tone: 'warn' } : { text: 'Everything here is live', tone: 'ok' }; },
            actions: function () { return { secondary: null, primary: { label: 'Publish', icon: 'cloud-upload', disabled: A.dirtyCount() === 0, title: 'Put your changes on the live website' } }; },
            runPrimary: function () {
                if (!A.dirtyCount()) return;
                confirmPublish('Publish your changes to the live website?', function () {
                    for (var id in edits.person) { var e0 = edits.person[id]; if (!S.allowIncomplete && e0.name != null && !e0.name.trim()) { toast('A person’s name cannot be empty.', 'error'); return; } }
                    for (var k in edits.sec) { if (!S.allowIncomplete && edits.sec[k].title != null && !edits.sec[k].title.trim()) { toast('A heading cannot be empty.', 'error'); return; } }
                    run('Publishing…', function () {
                        var p = Promise.resolve(), errs = [], done = 0;
                        function step(fields, files) { p = p.then(function () { return sendContent(fields, files).then(function (r) { if (r.ok) { done++; if (r.sections) syncSettings(r.sections); } else errs.push(r.error); }); }); }
                        Object.keys(edits.sec).forEach(function (k) { var s = secBy(k), e = edits.sec[k]; step({ action: 'admin_save_section', section_key: k, title: e.title != null ? e.title : s.title, description: e.desc != null ? e.desc : s.desc }); });
                        Object.keys(edits.person).forEach(function (id) {
                            var r = personBy(+id), e = edits.person[id]; if (!r) return;
                            step({ action: 'admin_save_person', person_id: id, section_key: r.sec.key, name: e.name != null ? e.name : r.p.name, role: e.role != null ? e.role : r.p.role, remove_photo: e.remove ? '1' : '' }, { photo: e.file });
                        });
                        Object.keys(edits.office).forEach(function (id) {
                            var r = officeBy(+id), e = edits.office[id]; if (!r) return; var o = r.o, c = o.contact || {};
                            step(Object.assign({ action: 'admin_save_office', office_id: id, section_key: r.sec.key, office_name: o.office, head_name: o.name, head_role: o.role, phone: c.phone, email: c.email, location: c.location, hours: c.hours, services: (o.services || []).join('\n') }, e.details || {}, { remove_photo: e.remove ? '1' : '' }), { photo: e.file });
                        });
                        Object.keys(edits.order).forEach(function (k) { step({ action: 'admin_reorder', section_key: k, order: JSON.stringify(edits.order[k]) }); });
                        return p.then(function () {
                            errs.forEach(function (m) { toast(m, 'error'); });
                            if (done && !errs.length) { toast('Published. Your changes are live now.', 'ok'); }
                            if (!errs.length) edits = { sec: {}, person: {}, office: {}, order: {} };
                            if (done) reload();
                        });
                    });
                });
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: Departments and Programs (real page + the existing manager on demand) ══════════════════
        function libraryIconPicker(choices, current, apply) {
            ensureMaterialIcons();
            var visible = 80;
            var search = h('input', { type: 'search', class: 'form-control form-control-sm', placeholder: 'Search icons: book, science, building…', 'aria-label': 'Search icons', on: { input: function () { visible = 80; draw(); } } });
            var family = h('select', { class: 'form-select form-select-sm', 'aria-label': 'Icon library', on: { change: function () { visible = 80; draw(); } } }, [
                h('option', { value: '', text: 'All icon libraries' }), h('option', { value: 'material', text: 'Material Symbols' }),
                h('option', { value: 'bi', text: 'Bootstrap Icons' }), Object.keys(choices).some(function (key) { return !!choices[key].path; }) ? h('option', { value: 'svg', text: 'Original icons' }) : null
            ]);
            var grid = h('div', { class: 've-icon-grid ve-academic-icon-grid', role: 'group', 'aria-label': 'Choose an icon' });
            var count = h('small', { class: 've-pop-hint', 'aria-live': 'polite' });
            var more = h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Show more icons', on: { click: function () { visible += 80; draw(); } } });
            var keys = Object.keys(choices).sort(function (a, b) {
                var rank = { svg: 0, material: 1, bi: 2 };
                return rank[a.split(':')[0]] - rank[b.split(':')[0]] || choices[a].label.localeCompare(choices[b].label);
            });
            function choiceButton(spec) {
                var choice = choices[spec];
                var library = choice.path ? 'Original' : choice.material ? 'Material Symbols' : 'Bootstrap Icons';
                var glyph = choice.path ? h('img', { src: choice.path, alt: '', 'aria-hidden': 'true' })
                    : choice.material ? h('span', { class: 'material-symbols-outlined ve-micon-glyph', text: choice.material, 'aria-hidden': 'true' }) : icon(choice.glyph);
                return h('button', { type: 'button', class: 've-icon-opt ve-feature-icon-opt' + (spec === current ? ' is-on' : ''), title: choice.label + ' (' + library + ')', 'aria-label': choice.label + ' (' + library + ')', 'aria-pressed': spec === current ? 'true' : 'false', on: { click: function () {
                    apply(spec, choice); closePop(); renderToolbar(); chrome();
                } } }, [glyph]);
            }
            function draw() {
                var query = String(search.value || '').trim().toLowerCase().replace(/[_-]+/g, ' ');
                var matches = keys.filter(function (spec) {
                    var haystack = (choices[spec].label + ' ' + spec).toLowerCase().replace(/[_-]+/g, ' ');
                    return (!family.value || spec.split(':')[0] === family.value) && (!query || haystack.indexOf(query) !== -1);
                });
                grid.replaceChildren.apply(grid, matches.slice(0, visible).map(choiceButton));
                count.textContent = matches.length ? 'Showing ' + Math.min(visible, matches.length) + ' of ' + matches.length + ' icons' : 'No matching icons. Try another search or library.';
                more.hidden = matches.length <= visible;
            }
            draw();
            popBelowToolbar(h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'Choose an icon' }),
                search, family, count, grid, more, h('small', { class: 've-pop-hint', text: 'Your selection is saved when you press Publish.' })]), null, true);
            search.focus();
        }
        function ensureMaterialIcons() {
            if (document.getElementById('ve-micon-css')) return;
            var l = document.createElement('link');
            l.id = 've-micon-font'; l.rel = 'stylesheet';
            l.href = 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block';
            document.head.appendChild(l);
            var st = document.createElement('style'); st.id = 've-micon-css';
            st.textContent = '.ve-micon-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:6px;margin:6px 0 10px;max-height:260px;overflow-y:auto;overflow-x:hidden}' +
                '.ve-micon-btn{display:flex;align-items:center;justify-content:center;height:52px;padding:0;border:1px solid #d5dbd7;border-radius:8px;background:#fff;color:#1f3d2b;cursor:pointer}' +
                '.ve-micon-btn:hover,.ve-micon-btn:focus-visible{background:#eef5f0;border-color:#2e7d4f;outline:none}' +
                '.ve-micon-btn.is-on{background:#dff0e5;border-color:#2e7d4f;box-shadow:0 0 0 1px #2e7d4f inset}' +
                '.ve-micon-glyph{font-family:"Material Symbols Outlined";font-weight:400;font-style:normal;font-size:28px;line-height:1;letter-spacing:normal;text-transform:none;white-space:nowrap;direction:ltr;font-feature-settings:"liga";-webkit-font-feature-settings:"liga";-webkit-font-smoothing:antialiased;display:inline-block;width:28px;overflow:hidden}';
            document.head.appendChild(st);
        }
    function EntityAdapter() {
        var departmentOrder = null;
        // ── Programs: slide strips under the page (top banner + Facilities), like the homepage slides ──
        var dock = $('#veDock');
        var PROG_URL = '/admin/action/program-page-handler.php';   // same endpoint as the tabs in "More settings"
        var LISTS = {
            banner: {
                label: 'Banner slides', icon: 'collection-play', itemType: 'hero_slide', data: null, sequential: true,
                accept: 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm'
            },
            facility: {
                label: 'Facility slides', icon: 'images', itemType: 'facility', data: null, sequential: true,
                accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'
            },
            uniform: {
                label: 'Uniform photos', icon: 'person-badge', itemType: 'uniform', data: null, sequential: true,
                accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'
            }
        };
        var tab = 'banner';   // which strip is showing (kept while the preview reloads)
        var slideReplacements = {};
        function replacementKey(L, slide) { return L.itemType + ':' + slide.item_id; }
        function previewReplacement(L, index, change) {
            var node = S.doc.querySelectorAll(L.itemType === 'hero_slide' ? '.hero-slide' : L.itemType === 'uniform' ? '.template-uniform-card img' : '.fcar-slide img')[index];
            if (!node) return;
            if (L.itemType !== 'hero_slide') { node.src = change.url; return; }
            var video = /^video\//.test(change.file.type) || /\.(mp4|webm)$/i.test(change.file.name);
            // Keep the carousel's original node: its navigation holds references to it.
            if (node.tagName === 'VIDEO') {
                if(video){node.removeAttribute('poster');node.src=change.url;node.muted=true;node.loop=true;node.play().catch(function(){});}
                else {node.pause();node.removeAttribute('src');node.load();node.poster=change.url;}
                return;
            }
            var preview=node.querySelector('[data-ve-replacement-video]');if(preview)preview.remove();
            if(video){
                preview=S.doc.createElement('video');preview.setAttribute('data-ve-replacement-video','');preview.style.cssText='width:100%;height:100%;object-fit:cover';
                preview.src=change.url;preview.muted=true;preview.loop=true;preview.setAttribute('playsinline','');node.appendChild(preview);preview.play().catch(function(){});
            } else { node.style.backgroundImage='url('+JSON.stringify(change.url)+')';node.setAttribute('data-bg',change.url); }
        }
        function restoreSlideReplacements() {
            Object.keys(LISTS).forEach(function(key){var L=LISTS[key];if(L.data)L.data.slides.forEach(function(slide,index){var change=slideReplacements[replacementKey(L,slide)];if(change)previewReplacement(L,index,change);});});
            renderStrip();
        }
        async function replaceSlide(L, index) {
            if (S.busy || !L.data || !await guardDrawer(L)) return;
            var slide=L.data.slides[index], route=S.url;
            fileInput.multiple=false;
            pickFile(L.accept,function(file){
                if(S.busy || S.url!==route || !L.data.slides.some(function(row){return row.item_id===slide.item_id;}))return;
                var problem=fileProblem(L,file);if(problem){toast('Choose another file: '+problem+'.','error');return;}
                var key=replacementKey(L,slide), previous=slideReplacements[key];
                slideReplacements[key]={file:file,url:previewUrl(file)};
                previewReplacement(L,index,slideReplacements[key]);
                if(previous)URL.revokeObjectURL(previous.url);
                closePop();renderStrip();chrome();
                toast('Slide replaced in the preview. Save draft or Publish when ready.','ok');
            },false,S.doc.querySelectorAll(L.itemType==='hero_slide'?'.hero-slide':L.itemType==='uniform'?'.template-uniform-card img':'.fcar-slide img')[index]);
        }

        function readData(doc, id) {
            try { var el = doc.getElementById(id); return el ? JSON.parse(el.textContent) : null; } catch (e) { return null; }
        }
        function nameFromFile(n) { n = String(n).replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim(); return n ? n.charAt(0).toUpperCase() + n.slice(1, 120) : 'Slide'; }
        function pickFiles(accept, cb) {
            fileInput.value = ''; fileInput.setAttribute('accept', accept); fileInput.multiple = true;
            fileInput.removeAttribute('data-photo-check');
            if (window.GNCImageFraming) {
                var target = S.doc && S.doc.querySelector(tab === 'banner'
                    ? '.hero-slide.is-active,.hero-slides,.template-hero'
                    : '.fcar-slide img,.fcar-track img,.fcar-viewport');
                window.GNCImageFraming.setUploadTarget(fileInput, target);
            }
            fileInput.onchange = function () {
                var fs = Array.prototype.slice.call(fileInput.files || []);
                fileInput.multiple = false; fileInput.onchange = null;
                if (fs.length) cb(fs);
            };
            fileInput.click();
        }
        /** Why a file cannot be used for this strip ('' = fine). */
        function fileProblem(L, f) {
            var m = /\.([a-z0-9]+)$/i.exec(f.name), ext = m ? m[1].toLowerCase() : '';
            var banner = L.itemType === 'hero_slide';
            var isImg = ['jpg', 'jpeg', 'png', 'webp'].indexOf(ext) !== -1 || (banner && ext === 'gif');
            var isVid = banner && (ext === 'mp4' || ext === 'webm');
            if (!isImg && !isVid) return banner ? 'not a JPG, PNG, WEBP, GIF, MP4 or WEBM file' : 'not JPG, PNG or WEBP';
            if (isVid && f.size > 50 * MB) return 'video over 50 MB';
            if (isImg && f.size > 5 * MB) return 'over 5 MB';
            return '';
        }
        /** The Facilities tab in "More settings" keeps its own copy of that list; warn before replacing unsaved edits there. */
        async function guardDrawer(L) {
            if (L.itemType !== 'facility') return true;
            var d = D.querySelector('.pgm-card[data-pid="' + L.data.program_id + '"] .pgm-panel[data-panel="facility"] .pgm-dirty');
            return !(d && !d.hidden) || await window.AdminDialogs.confirm('The Facilities tab in “More settings” has changes you have not saved. Continuing will discard them.\n\nContinue?');
        }
        function itemToRow(i) { return { item_id: i.item_id, title: i.title, subtitle:i.subtitle || '', body: i.body || '', icon:i.icon || '', letter:i.letter || '', file: null }; }
        function rowsOf(list) { return list.map(itemToRow); }

        /** Send the whole list (the server keeps rows by item_id, adds item_id 0, and removes the ones left out). */
        function postRows(L, rows) {
            var fields = {
                action: 'save_program_items', program_id: L.data.program_id, item_type: L.itemType,
                items: JSON.stringify(rows.map(function (r) { return { item_id: r.item_id || 0, title: r.title, subtitle: r.subtitle || '', body: r.body || '', icon: r.icon || '', letter: r.letter || '', remove_image: false }; }))
            };
            var files = {};
            rows.forEach(function (r, n) { if (r.file) files['file_' + n] = r.file; });
            return send(PROG_URL, fields, files);
        }
        function afterSave(L, res) {
            // keep the Facilities tab in "More settings" in step, so saving there later cannot undo this
            if (L.itemType === 'facility') D.dispatchEvent(new CustomEvent('ve:facilities-changed', { detail: { pid: L.data.program_id, items: res.items || [] } }));
        }
        /** One save, then show the result in the preview. */
        function commit(L, rows, okMsg) {
            if(Object.keys(slideReplacements).length){toast('Publish or discard the pending slide replacements before changing slide names, order or count.','error');return Promise.resolve();}
            return run('Saving slides…', function () {
                return postRows(L, rows).then(function (res) {
                    if (!res.ok) { toast(res.error, 'error'); return; }
                    afterSave(L, res);
                    toast(okMsg, 'ok');
                    loadFrame(S.url, true);
                });
            });
        }
        /** Big files (videos): upload one at a time so a single request never gets too large for the server. */
        function commitSequential(L, base, newRows) {
            if(Object.keys(slideReplacements).length){toast('Publish or discard the pending slide replacements before adding slides.','error');return Promise.resolve();}
            return run('Uploading…', function () {
                var cur = base.slice(), done = 0, last = null;
                return newRows.reduce(function (p, nr, idx) {
                    return p.then(function (go) {
                        if (!go) return false;
                        setStatus('Uploading ' + (idx + 1) + ' of ' + newRows.length + '…', 'busy');
                        return postRows(L, cur.concat([nr])).then(function (res) {
                            if (!res.ok) { toast(res.error + (done ? ' (' + plural(done, 'slide') + ' added before this one.)' : ''), 'error'); return false; }
                            done++; last = res; cur = (res.items || []).map(itemToRow); return true;
                        });
                    });
                }, Promise.resolve(true)).then(function () {
                    if (!done) return;
                    afterSave(L, last);
                    toast(plural(done, 'slide') + ' added and live. Click a thumbnail to rename it.', 'ok');
                    loadFrame(S.url, true);
                });
            });
        }

        async function addSlides() {
            var L = LISTS[tab]; if (!L || !L.data) return;
            if (L.data.slides.length >= L.data.limit) { toast('This slideshow is full (' + L.data.limit + ' slides). Delete one to add another.', 'error'); return; }
            if (!await guardDrawer(L)) return;
            pickFiles(L.accept, function (files) {
                var base = rowsOf(L.data.slides), room = L.data.limit - base.length, fresh = [], skipped = [];
                files.forEach(function (f) {
                    if (fresh.length >= room) { skipped.push(f.name + ' (the slideshow is full)'); return; }
                    var why = fileProblem(L, f);
                    if (why) { skipped.push(f.name + ' (' + why + ')'); return; }
                    fresh.push({ item_id: 0, title: nameFromFile(f.name), body: '', file: f });
                });
                if (skipped.length) toast('Skipped: ' + skipped.join(', ') + '.', 'error');
                if (!fresh.length) return;
                if (L.sequential) commitSequential(L, base, fresh);
                else commit(L, base.concat(fresh), plural(fresh.length, 'slide') + ' added and live. Click a thumbnail to rename it.');
            });
        }
        async function moveSlide(from, to) {
            var L = LISTS[tab]; if (!L || !L.data) return;
            var n = L.data.slides.length;
            if (from === to || from < 0 || to < 0 || from >= n || to >= n) return;
            if (!await guardDrawer(L)) return;
            var rows = rowsOf(L.data.slides), m = rows.splice(from, 1)[0]; rows.splice(to, 0, m);
            commit(L, rows, 'Order saved.');
        }
        /** Bring a slide to the front of its slideshow in the preview. */
        function showSlide(L, i) {
            try {
                if (L.itemType === 'hero_slide') {
                    S.win.scrollTo({ top: 0, behavior: 'smooth' });
                    var hd = S.doc.querySelectorAll('.hero-dot'); if (hd[i]) hd[i].click();
                } else {
                    var sec = S.doc.getElementById(L.itemType==='uniform'?'template-uniform':'facilities'); if (sec) sec.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    if(L.itemType==='facility'){var dots = S.doc.querySelectorAll('.fcar-dot'); if (dots[i]) dots[i].click();}
                }
            } catch (e) {}
        }
        function openSlide(i, btn) {
            var L = LISTS[tab], s = L && L.data ? L.data.slides[i] : null; if (!s) return;
            showSlide(L, i);
            var needName = L.itemType === 'facility';
            var input = h('input', { class: 'form-control form-control-sm', type: 'text', value: s.title, maxlength: '255' });
            var description=needName?h('textarea',{class:'form-control form-control-sm',maxlength:'500',rows:'3','aria-label':'Facility description',placeholder:'Description (optional)'}):null;
            if(description)description.value=s.body||'';
            var go = async function () {
                var v = input.value.trim();
                if (!v && needName) { input.focus(); return; }
                if (v === s.title && (!description || description.value===(s.body||''))) { closePop(); return; }
                if (!await guardDrawer(L)) return;
                closePop();
                var rows = rowsOf(L.data.slides); rows[i].title = v;
                if(description)rows[i].body=description.value;
                confirmPublish('Publish your changes to the live website?', function () { commit(L, rows, 'Published. Your changes are live now.'); });
            };
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
            var del = h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: async function () {
                closePop();
                if (!await guardDrawer(L)) return;
                if (!await window.AdminDialogs.confirm('Delete “' + (s.title || 'this slide') + '” for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
                var rows = rowsOf(L.data.slides); rows.splice(i, 1);
                commit(L, rows, 'Slide deleted.');
            } } }, [icon('trash'), ' Delete']);
            var r = btn.getBoundingClientRect();
            var reposition = null;
            var replace = h('button', {type:'button',class:'btn btn-sm btn-outline-secondary',text:'Replace slide',on:{click:function(){replaceSlide(L,i);}}});
            if (window.GNCImageFraming && L.itemType === 'hero_slide') {
                var bannerImage = S.doc.querySelectorAll('.hero-slide')[i];
                if (bannerImage && bannerImage.tagName !== 'VIDEO') {
                    reposition = h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Reposition image', on: { click: function () {
                        closePop(); window.GNCImageFraming.edit(bannerImage);
                    } } });
                }
            }
            openPop(h('div', { class: 've-pop-form' }, [
                h('label', { class: 've-pop-label', text: 'Slide ' + (i + 1) + (needName ? ' name' : ' name (optional)') }),
                input,
                description,
                h('div', { class: 've-pop-row' }, [replace, reposition, h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Publish', on: { click: go } }), del])
            ]), r.left, r.top - 150);
        }

        // small style for the two tabs in the strip
        (function () {
            if (D.getElementById('veDockTabsCss')) return;
            var st = D.createElement('style'); st.id = 'veDockTabsCss';
            st.textContent = '.ve-dock-tabs{display:flex;flex-direction:column;align-items:flex-start;gap:.2rem}' +
                '.ve-dock-tab{display:inline-flex;align-items:center;gap:.4rem;border:0;background:transparent;color:inherit;font:inherit;letter-spacing:inherit;text-transform:inherit;padding:.2rem .45rem;border-radius:6px;opacity:.55;cursor:pointer;white-space:nowrap}' +
                '.ve-dock-tab:hover{opacity:1}.ve-dock-tab.is-on{opacity:1;background:rgba(9,64,36,.10)}';
            D.head.appendChild(st);
        })();

        function renderStrip() {
            if (!dock) return;
            var any = LISTS.banner.data || LISTS.facility.data || LISTS.uniform.data;
            if (!any) { dock.hidden = true; dock.innerHTML = ''; return; }
            if (!LISTS[tab] || !LISTS[tab].data) tab = LISTS.banner.data ? 'banner' : 'facility';
            var L = LISTS[tab];
            dock.hidden = false; dock.innerHTML = '';
            dock.appendChild(h('div', { class: 've-dock-label ve-dock-tabs', role: 'tablist' }, Object.keys(LISTS).filter(function (k) { return LISTS[k].data; }).map(function (k) {
                return h('button', { type: 'button', role: 'tab', 'aria-selected': k === tab ? 'true' : 'false', class: 've-dock-tab' + (k === tab ? ' is-on' : ''),
                    on: { click: function () { if (tab !== k) { closePop(); tab = k; renderStrip(); } } } },
                    [icon(LISTS[k].icon), h('span', { text: LISTS[k].label + ' (' + LISTS[k].data.slides.length + ')' })]);
            })));
            var track = h('div', { class: 've-dock-track', role: 'list' });
            L.data.slides.forEach(function (s, i) {
                var change=slideReplacements[replacementKey(L,s)], mediaUrl=change?change.url:s.image_url;
                var isVideo=change?/\.(mp4|webm)$/i.test(change.file.name):s.media_type==='video';
                var media = isVideo ? h('span', { class: 've-thumb-img ve-thumb-video' }, [icon('camera-video')])
                    : (mediaUrl ? h('img', { class: 've-thumb-img', src: mediaUrl, alt: '' }) : h('span', { class: 've-thumb-img ve-thumb-video' }, [icon('image')]));
                var b = h('button', { type: 'button', class: 've-thumb', draggable: 'true', role: 'listitem', 'data-i': i,
                    title: 'Click for slide options · drag to change the order',
                    on: { click: function () { openSlide(i, b); } } }, [
                    media, h('span', { class: 've-thumb-n', text: String(i + 1) }),
                    h('span', { class: 've-thumb-t', text: (change?'* ':'')+clip(s.title || 'Untitled', 22) })]);
                b.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(i)); e.dataTransfer.effectAllowed = 'move'; b.classList.add('is-drag'); });
                b.addEventListener('dragend', function () { b.classList.remove('is-drag'); $$('.ve-thumb', dock).forEach(function (x) { x.classList.remove('is-over'); }); });
                b.addEventListener('dragover', function (e) { e.preventDefault(); b.classList.add('is-over'); });
                b.addEventListener('dragleave', function () { b.classList.remove('is-over'); });
                b.addEventListener('drop', function (e) { e.preventDefault(); var from = parseInt(e.dataTransfer.getData('text/plain'), 10); if (!isNaN(from)) moveSlide(from, i); });
                track.appendChild(b);
            });
            dock.appendChild(track);
            var full = L.data.slides.length >= L.data.limit;
            dock.appendChild(h('button', { type: 'button', class: 've-add-slide', disabled: full,
                title: full ? 'This slideshow is full' : (L.itemType === 'hero_slide' ? 'Choose pictures or videos' : 'Choose one or more pictures'), on: { click: addSlides } },
                [icon('plus-lg'), h('span', { text: 'Add slides' })]));
        }

        // "Add slides" on the Facilities carousel inside the page
        window.addEventListener('message', function (ev) {
            if (ev.origin !== window.location.origin || !S.frame || ev.source !== S.frame.contentWindow) return;
            var d = ev.data;
            if (!d || d.source !== 'gnc-program-preview' || d.action !== 'facilities') return;
            if (d.mode === 'add' && LISTS.facility.data) { tab = 'facility'; renderStrip(); addSlides(); }
            if (d.mode === 'edit' && LISTS.facility.data) { tab='facility';renderStrip();var first=dock.querySelector('.ve-thumb');if(first)openSlide(0,first); }
        });
        // slides saved from the Facilities tab in "More settings": show them in the preview and the strip
        D.addEventListener('pgm:items-saved', function (ev) {
            if (ev.detail && ev.detail.type === 'facility') loadFrame(S.url, true);
        });

        // ══════════ In-place editing of departments, Basic Education and programs ══════════
        // The page prints the CURRENT stored values (veDeptData / veProgData) for managers. Both save endpoints replace the whole
        // record, so every save sends those values with the edits merged in. The same endpoints as the panel's forms are used.
        var mode = '';                    // 'dept' | 'prog' | 'deptlist' | ''
        var DD = null, PD = null;
        var TD = null, textChanges = {};
        var departmentPrograms = {}, nextProgramDraft = 0;
        var officeChange = null;
        var BD = null, bChange = {}, bFiles = {}, bLogos = {};
        var dChange = {}, dFiles = {}, dRemove = {};   // department + head of office: edited texts, new pictures, removed pictures
        var pChange = {}, pMeta = {}, pRows = {}, pPdf = null, pMedia = {};       // program: edited texts, name/colour/visibility, edited cards by list
        function resetPending() { pPdf=null;pMedia={};slideReplacements={};departmentPrograms = {}; nextProgramDraft = 0; textChanges = {}; officeChange = null; dChange = {}; dFiles = {}; dRemove = {}; pChange = {}; pMeta = {}; pRows = {}; bChange = {}; bFiles = {}; bLogos = {}; }

        var DFIELD = { 'dept-name': 'dept_name', 'dept-hero': 'hero_text', 'dept-about': 'about_text', 'dept-office-label': 'office_label',
            'dean-name': 'dean_name', 'dean-title': 'dean_title', 'dean-bio': 'dean_bio', 'dean-phone': 'dean_phone',
            'dean-email': 'dean_email', 'dean-office': 'dean_office', 'dean-hours': 'dean_hours' };
        var DEPT_KEYS = ['dept_name', 'hero_text', 'about_text', 'office_label', 'facebook_url'];
        var DEAN_KEYS = ['dean_name', 'dean_title', 'dean_bio', 'dean_phone', 'dean_email', 'dean_office', 'dean_hours'];
        function own(o, k) { return Object.prototype.hasOwnProperty.call(o, k); }
        function dBase(k) { return DD[k] == null ? '' : String(DD[k]); }
        function dVal(k) { return own(dChange, k) ? dChange[k] : dBase(k); }
        function dSet(k, v) { if (v === dBase(k)) delete dChange[k]; else dChange[k] = v; }
        function pBase(k) { return PD.content[k] == null ? '' : String(PD.content[k]); }
        function pSet(k, v) { if (v === pBase(k)) delete pChange[k]; else pChange[k] = v; }

        // ── program cards (badges, core values, reasons, careers): rows are edited in a working copy ──
        var ITEM_FIELDS = ['title', 'subtitle', 'body', 'icon', 'letter'];
        var ITEM_LABEL = { badge: 'Badge', core_value: 'Core value', why: 'Reason', career: 'Career' };
        var ITEM_NEW = { badge: 'New badge', core_value: 'New value', why: 'New reason', career: 'New career' };
        var ITEM_ICON = { badge: true, core_value: true };
        var ITEM_IMAGE = { career: true };
        function curRows(type) { return pRows[type] || (PD && PD.items[type]) || []; }
        function cardRowsOf(type) { return pRows[type] || (pRows[type] = PD.items[type].map(function (r) { return JSON.parse(JSON.stringify(r)); })); }
        function rowsChanged(type) {
            var rows = pRows[type]; if (!rows) return false;
            var base = PD.items[type];
            if (rows.length !== base.length) return true;
            return rows.some(function (r, i) { return r.file || r.removeImg || ITEM_FIELDS.some(function (f) { return String(r[f] || '') !== String(base[i][f] || ''); }); });
        }
        function pruneRows() { Object.keys(pRows).forEach(function (t) { if (!rowsChanged(t)) delete pRows[t]; }); }
        function listDirty() { return Object.keys(pRows).filter(rowsChanged).length; }
        function pendingCount() {
            var n = Object.keys(slideReplacements).length + Object.keys(bChange).length + Object.keys(bFiles).length + Object.keys(bLogos).length;
            if (departmentOrder && departmentOrder.dirty()) n++;
            n += Object.keys(textChanges).length;
            n += Object.keys(departmentPrograms).filter(programCardDirty).length;
            if (officeChange) n++;
            if (DD) n += Object.keys(dChange).length + Object.keys(dFiles).length + Object.keys(dRemove).length;
            if (PD) n += Object.keys(pChange).length + Object.keys(pMeta).length + listDirty() + (pPdf?1:0) + Object.keys(pMedia).length;
            return n;
        }
        function setItemProp(ref, val) {
            var m = /^(\w+):(\d+):(\w+)$/.exec(ref || '');
            if (!m || !PD || !PD.items[m[1]]) return;
            var r = cardRowsOf(m[1])[+m[2]]; if (!r) return;
            r[m[3]] = val; pruneRows();
        }
        function itemRef(ref) { var m = /^(\w+):(\d+)/.exec(ref || ''); return m && PD && PD.items[m[1]] ? { type: m[1], i: +m[2] } : null; }

        function bindEditing(doc) {
            if (TD) ['text', 'logo'].forEach(function (part) {
                var element = doc.querySelector('[data-dept-header-' + part + ']');
                if (element) element.classList.toggle('dept-header-hidden', headerHidden('header_' + part + '_hidden'));
            });
            if (PD) {
                $$('.template-hero.has-banner,.fcar-slide img,.template-uniform-card img,.about-feature-seal-wrap', doc).forEach(function (el) {
                    el.setAttribute('data-ve-img', 'program-picture');
                });
            }
            $$('[data-ve-field]', doc).forEach(function (el) {
                var f = el.getAttribute('data-ve-field');
                if (DD && (f === 'dept-program-name' || f === 'dept-program-description')) {
                    var cardKey = el.getAttribute('data-ve-key'), property = f === 'dept-program-name' ? 'program_name' : 'description';
                    if (departmentPrograms[cardKey]) makeField(el, function (e, value) { departmentPrograms[cardKey][property] = value; });
                }
                else if (TD && f === 'dept-page-text') {
                    var textKey = el.getAttribute('data-ve-key');
                    if (TD.fields[textKey]) makeField(el, function (e, value) {
                        if (value === TD.fields[textKey].value) delete textChanges[textKey]; else textChanges[textKey] = value;
                    });
                }
                else if (BD && f === 'basic-text') {
                    var bk = el.getAttribute('data-ve-key');
                    if (BD.fields[bk]) makeField(el, function (e, val) {
                        if (val === BD.fields[bk].value) delete bChange[bk]; else bChange[bk] = val;
                    });
                }
                else if (DD && DFIELD[f]) {
                    var k = DFIELD[f], originalOverview = null;
                    makeField(el, function (e, val) {
                        // The header's uppercase prefix is presentation; retain the typed name in storage.
                        if (k === 'dept_name') val = String(e.textContent || '').replace(/\s+/g, ' ').trim();
                        dSet(k, originalOverview !== null && val === originalOverview ? dBase(k) : val);
                    });
                    if (el.hasAttribute('data-ve-overview')) originalOverview = readField(el);
                }
                else if (PD && f === 'prog-name') {
                    makeField(el, function (e, val) {
                        // innerText includes the heading's uppercase CSS; keep the typed casing in storage.
                        val = String(e.textContent || '').replace(/\s+/g, ' ').trim();
                        var current = String(metaVal('name')), prefix = (current.match(/^Bachelor of (?:Science|Arts)?\s*(?:in\s+)?/i) || [''])[0];
                        var name = e.getAttribute('data-ve-key') === 'short-name' ? prefix + val : val;
                        pMetaSet('name', name, PD.name);
                        $$('[data-ve-field="prog-name"]', doc).forEach(function (other) {
                            if (other !== e) { other.textContent = other.getAttribute('data-ve-key') === 'short-name' ? shortName(name) : name; markEmpty(other); }
                        });
                    });
                } else if (PD && f === 'prog-description') {
                    makeField(el, function (e, val) { pMetaSet('description', val, PD.description); });
                } else if (PD && f === 'prog-text') {
                    var key = el.getAttribute('data-ve-key');
                    if (PD.fields[key]) makeField(el, function (e, val) { pSet(key, val); });
                } else if (PD && f === 'prog-item') {
                    var ref = el.getAttribute('data-ve-key'), prop = (ref || '').split(':')[2];
                    if (!el.hasAttribute('data-ve-max')) el.setAttribute('data-ve-max', prop === 'body' ? '2000' : '255');
                    makeField(el, function (e, val) { setItemProp(ref, val); });
                }
            });
        }

        // ── small forms next to an element on the page ──
        function popAtEl(el, content, wide) {
            var fr = S.frame.getBoundingClientRect(), r = el.getBoundingClientRect();
            openPop(content, fr.left + r.left, fr.top + Math.max(8, Math.min(r.bottom + 8, fr.height - 280)), null, wide);
        }
        /** fields: [{name, label, value, max, multiline, placeholder, required, hint}] */
        function formPop(el, title, fields, cta, done, extra, wide) {
            var inputs = {};
            var go = function () {
                var out = {};
                for (var i = 0; i < fields.length; i++) {
                    var f = fields[i], v = inputs[f.name].value.trim();
                    if (f.required && !v) { inputs[f.name].focus(); return; }
                    out[f.name] = v;
                }
                closePop(); done(out);
            };
            var rows = fields.map(function (f) {
                var inp;
                if (f.options) {
                    inp = h('select', { class: 'form-select form-select-sm' }, f.options.map(function (o) {
                        return h('option', { value: o.value, text: o.label });
                    }));
                } else {
                    inp = h(f.multiline ? 'textarea' : 'input', { class: 'form-control form-control-sm', type: 'text', placeholder: f.placeholder || '', maxlength: f.max || 255, rows: 3 });
                }
                inp.value = f.value || (f.options && f.options[0] ? f.options[0].value : ''); inputs[f.name] = inp;
                if (!f.multiline && !f.options) inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
                return h('div', { class: 've-pop-form' }, [h('label', { class: 've-pop-label', text: f.label }), inp, f.hint ? h('small', { class: 've-pop-hint', text: f.hint }) : null]);
            });
            popAtEl(el, h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: title })].concat(rows,
                [h('div', { class: 've-pop-row' }, [h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: cta || 'Apply', on: { click: go } })].concat(extra || []))])), wide);
        }
        /** Before an action that reloads the page: warn if other unsaved edits would be lost. */
        async function okToReload(except) {
            if (drafts && drafts.saved()) return true;
            var others = pendingCount() - (except || 0);
            return others <= 0 || await window.AdminDialogs.confirm('You have other unsaved changes on this page. They will be lost when the page refreshes.\n\nContinue anyway?');
        }
        function routeNow() { return entityRoute(S.url); }
        function syncSub(url) {
            var s = $('#veSub'), want = entityRoute(url || S.url);
            if (!s || !want) return;
            Array.prototype.forEach.call(s.options, function (o) { var r = entityRoute(o.value); if (r && r.page === want.page && r.sub === want.sub) s.value = o.value; });
        }
        async function openPage(url) {
            var u; try { u = new URL(url, window.location.origin); } catch (e) { return; }
            if (!await guardDirty()) return;
            loadFrame(withParam(u.pathname + u.search, 'ce_preview', '1')); syncSub(u.pathname + u.search);
        }

        // ── departments ──
        function showPic(el, url) {
            var im = el.tagName === 'IMG' ? el : el.querySelector('img');
            el.style.opacity = '';
            if (!im && el.classList && el.classList.contains('be-empty-media')) {
                im = el.ownerDocument.createElement('img');
                im.alt = 'Selected image';
                im.className = 'is-loaded';
                im.style.cssText = 'display:block;width:100%;height:100%;object-fit:contain;opacity:1';
                if (el.classList.contains('dept-principal-img') || el.classList.contains('dept-dean-upload')) im.style.objectFit = 'cover';
                el.replaceChildren(im);
                el.classList.add('has-image');
                var wrap = el.closest('.skeleton-wrap'), group = el.closest('.skeleton-group');
                if (wrap) wrap.classList.add('is-loaded');
                if (group) group.classList.add('is-loaded');
            }
            if (im) { im.src = url; im.removeAttribute('srcset'); im.style.opacity = ''; }
            else { el.innerHTML = ''; el.style.backgroundImage = 'url("' + url + '")'; el.style.backgroundSize = 'contain'; el.style.backgroundRepeat = 'no-repeat'; el.style.backgroundPosition = 'center'; }
        }
        function previewDepartmentFacebook(el, url) {
            var current = el.closest('.dept-fb-box') || el;
            var replacement = S.doc.createElement('div');
            var picker = S.doc.createElement('div');
            picker.setAttribute('data-ve-pick', 'dept-facebook');
            picker.setAttribute('data-ve-label', 'Facebook page');
            if (url) {
                replacement.className = 'dept-fb-box';
                replacement.style.position = 'relative';
                var frame = S.doc.createElement('iframe');
                frame.src = 'https://www.facebook.com/plugins/page.php?href=' + encodeURIComponent(url)
                    + '&tabs=&width=500&height=130&small_header=false&adapt_container_width=true'
                    + '&hide_cover=true&show_facepile=false&show_posts=false';
                frame.width = '500'; frame.height = '130';
                frame.title = dVal('dept_name') + ' Facebook page';
                frame.setAttribute('scrolling', 'no');
                frame.setAttribute('allow', 'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share');
                frame.setAttribute('allowfullscreen', '');
                picker.title = 'Click to change the Facebook page';
                picker.style.cssText = 'position:absolute;inset:0;z-index:2;cursor:pointer';
                replacement.appendChild(frame);
                replacement.appendChild(picker);
            } else {
                replacement = picker;
                replacement.className = 've-add-tile';
                replacement.textContent = '+ Add the Facebook page link';
            }
            current.replaceWith(replacement);
        }
        function editFacebook() {
            var el = S.doc.querySelector('[data-ve-pick="dept-facebook"]'); if (!el || !DD) return;
            var cur = dVal('facebook_url');
            formPop(el, 'Facebook page', [{ name: 'url', label: 'Address of the Facebook page', value: cur, max: 255, placeholder: 'https://www.facebook.com/your-page',
                hint: 'Shows the page’s posts on this department’s page. Leave empty to remove it.' }], 'Apply', function (o) {
                var v = o.url.trim();
                if (v && !/^https?:\/\/(www\.)?facebook\.com\//i.test(v)) { toast('That is not a Facebook page address. It should start with https://www.facebook.com/', 'error'); return; }
                dSet('facebook_url', v); previewDepartmentFacebook(el, v); renderToolbar(); chrome();
                toast(v ? 'Link set. Press Publish to show it on the website.' : 'The Facebook box will be removed when you publish.', 'ok');
            });
        }
        function statementIconPicker(sel) {
            var program = sel.kind === 'program-statement-icon';
            var data = program ? PD : TD;
            if (!data || !data.icons || !/^(vision|mission)_icon$/.test(sel.key)) return;
            var current = program ? (own(pChange, sel.key) ? pChange[sel.key] : pBase(sel.key) || sel.el.getAttribute('data-ve-value'))
                : (own(textChanges, sel.key) ? textChanges[sel.key] : TD.fields[sel.key].value);
            libraryIconPicker(data.icons, current, function (spec, choice) {
                if (program) pSet(sel.key, spec);
                else if (spec === TD.fields[sel.key].value) delete textChanges[sel.key]; else textChanges[sel.key] = spec;
                var node = S.doc.createElement(choice.material ? 'span' : 'i');
                var glyphClass = program ? 'template-vm-icon-glyph' : 'dept-statement-icon-glyph';
                node.setAttribute('aria-hidden', 'true');
                node.className = (choice.material ? 'material-symbols-outlined' : 'bi bi-' + choice.glyph) + ' ' + glyphClass;
                if (choice.material) node.textContent = choice.material;
                sel.el.replaceChildren(node);
                sel.el.setAttribute('data-ve-value', spec);
            });
        }
        function academicIconPicker(sel) {
            if (!BD || !BD.fields[sel.key] || !BD.icons) return;
            var current = own(bChange, sel.key) ? bChange[sel.key] : BD.fields[sel.key].value;
            libraryIconPicker(BD.icons, current, function (spec, choice) {
                if (spec === BD.fields[sel.key].value) delete bChange[sel.key]; else bChange[sel.key] = spec;
                var node = S.doc.createElement(choice.path ? 'img' : choice.material ? 'span' : 'i');
                node.setAttribute('aria-hidden', 'true');
                if (choice.path) { node.src = choice.path; node.alt = ''; }
                else if (choice.material) { node.className = 'material-symbols-outlined'; node.textContent = choice.material; }
                else node.className = 'bi bi-' + choice.glyph;
                sel.el.replaceChildren(node);
            });
        }
        function editOfficeLocation(el) {
            if (!DD || !DD.can_edit_office) return;
            var current = officeChange || DD.office_location;
            var buildings = DD.office_buildings || {};
            var options = [{ value: '', label: 'Select a campus building' }].concat(Object.keys(buildings).map(function (id) {
                return { value: id, label: buildings[id] };
            }));
            formPop(el, 'Office location', [
                { name: 'name', label: 'Office name', value: current.name, max: 100, required: true },
                { name: 'building_id', label: 'Campus building', value: current.building_id, options: options, required: true },
                { name: 'floor', label: 'Floor', value: current.floor, max: 40 },
                { name: 'room', label: 'Room', value: current.room, max: 40 },
                { name: 'details', label: 'Additional location details', value: current.details, max: 500, multiline: true }
            ], 'Apply', function (values) {
                var address = [buildings[values.building_id], values.floor ? 'Floor ' + values.floor : '', values.room ? 'Room ' + values.room : '', values.details].filter(Boolean).join(', ');
                if (address.length > 255) { toast('The full office address must be at most 255 characters.', 'error'); return; }
                officeChange = Object.keys(values).some(function (key) { return values[key] !== DD.office_location[key]; }) ? values : null;
                el.querySelector('[data-office-name]').textContent = values.name;
                el.querySelector('[data-office-address]').textContent = address;
                renderToolbar(); chrome();
            }, null, true);
        }
        function addDepartment(el) {
            formPop(el, 'Add a department', [{ name: 'name', label: 'Department name', max: 255, required: true, placeholder: 'e.g. College of Nursing' }], 'Create', async function (o) {
                if (!await okToReload()) return;
                run('Creating…', function () {
                    return sendContent({ action: 'save_department_details', dept_id: 0, dept_name: o.name, description: '', hero_text: '', about_text: '', facebook_url: '', office_label: '', theme_color: '' }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Department created. It is live: click its text to fill the page in.', { page: 'college-departments', sub: (r.department && r.department.slug) || '' });
                    });
                });
            });
        }
        function programCardDirty(key) {
            var row = departmentPrograms[key];
            return !!(row && (row.added || row.file || row.removeImage || row.program_name !== row.baseName || row.description !== row.baseDescription));
        }
        function appendProgramCard(doc, added, savedKey, savedRecord) {
            var template = doc.getElementById('veDepartmentProgramTemplate'), grid = doc.querySelector('[data-ve-program-grid]');
            if (!template || !grid) return;
            var key = savedKey || ('draft-' + (++nextProgramDraft)), fragment = template.content.cloneNode(true);
            var card = fragment.querySelector('[data-ve-program-card]');
            card.setAttribute('data-ve-program-card', key);
            if (!added) card.setAttribute('data-warning-placeholder', 'true');
            Array.prototype.forEach.call(card.querySelectorAll('[data-ve-key]'), function (el) { el.setAttribute('data-ve-key', key); });
            card.querySelector('[data-ve-program-image]').setAttribute('data-ve-program-image', key);
            departmentPrograms[key] = savedRecord || { program_id: 0, program_name: '', description: '', baseName: '', baseDescription: '', hasImage: false, added: added };
            grid.insertBefore(fragment, grid.querySelector('[data-ve-add="program"]'));
            bindEditing(doc);
            if (added) card.querySelector('h3').focus();
        }
        function departmentProgramActions(items, key) {
            var row = departmentPrograms[key]; if (!row) return;
            if (row.url) items.push({ icon: 'pencil-square', label: 'Edit this program', primary: true, run: function () { openPage(row.url); } });
            items.push({ icon: 'trash', label: 'Delete program', title: 'Delete this program', danger: true, run: async function () {
                if (!await window.AdminDialogs.confirm('Delete “' + (row.program_name || 'this empty program card') + '” for good?\n\n' + (row.program_id ? 'The whole program page is removed from the website immediately. This cannot be undone.' : 'This unpublished card will be removed.'))) return;
                if (!row.program_id) {
                    var card = S.doc.querySelector('[data-ve-program-card="' + key + '"]');
                    if (card) card.remove();
                    delete departmentPrograms[key]; select(null); chrome(); return;
                }
                if (!await okToReload(programCardDirty(key) ? 1 : 0)) return;
                run('Deleting program…', function () {
                    return send(PROG_URL, { action: 'delete_program', program_id: row.program_id }).then(function (response) {
                        if (!response.ok) { toast(response.error, 'error'); return; }
                        reloadEditor('Program deleted.', routeNow());
                    });
                });
            } });
        }
        function addProgram(el) {
            if (BD) return;
            if (DD && S.doc.querySelector('[data-ve-program-grid]')) {
                appendProgramCard(S.doc, true); renderToolbar(); chrome(); return;
            }
            var units = Array.isArray(C.programDepartments) ? C.programDepartments : [];
            var options = units.map(function (unit) {
                return { value: String(unit.id), label: unit.name };
            });
            var selectedUnit = DD && DD.dept_id ? String(DD.dept_id) : '';
            formPop(el, 'Add a program', [
                { name: 'department', label: 'Department', value: selectedUnit, required: true, options: options },
                { name: 'code', label: 'Short code', max: 10, required: true, placeholder: 'e.g. BSIT', hint: '2 to 10 letters or numbers (a dash is allowed).' },
                { name: 'name', label: 'Full program name', max: 255, required: true, placeholder: 'e.g. Bachelor of Science in Information Technology' }], 'Create', async function (o) {
                if (!await okToReload()) return;
                var deptId = Number(o.department || 0);
                if (deptId <= 0) { toast('Choose a college or Graduate School department.', 'error'); return; }
                run('Creating…', function () {
                    return send(PROG_URL, { action: 'create_program', program_code: o.code, program_name: o.name, dept_id: deptId, theme: 'green', theme_custom: '' }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Program created. Click its text to fill the page in.', { page: 'programs', program: r.program_id });
                    });
                });
            });
        }
        function headerHidden(key) {
            return TD && TD.fields[key] && (own(textChanges, key) ? textChanges[key] : TD.fields[key].value) === '1';
        }
        function toggleHeaderPart(part) {
            var key = 'header_' + part + '_hidden', value = headerHidden(key) ? '0' : '1';
            if (!TD || !TD.fields[key]) return;
            if (value === TD.fields[key].value) delete textChanges[key]; else textChanges[key] = value;
            var el = S.doc.querySelector('[data-dept-header-' + part + ']');
            if (el) el.classList.toggle('dept-header-hidden', value === '1');
            select(null); closePop(); chrome();
        }
        function deptOptions(x, y) {
            if (!DD || (DD.kind !== 'college' && DD.kind !== 'graduate')) return;
            var hidden = !!DD.archived;
            var toggle = h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', on: { click: async function () {
                closePop(); if (!await okToReload()) return;
                run(hidden ? 'Showing…' : 'Hiding…', function () {
                    return sendContent({ action: 'archive_department', dept_id: DD.dept_id, archived: hidden ? '0' : '1' }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor(r.message || 'Done.', routeNow());
                    });
                });
            } } }, [icon(hidden ? 'eye' : 'eye-slash'), hidden ? ' Show on the website' : ' Hide from the website']);
            var del = h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: async function () {
                closePop();
                if (!await window.AdminDialogs.confirm('Delete “' + DD.dept_name + '” for good?\n\nIt is removed from the website straight away. Its programs stay but lose this department. This cannot be undone.')) return;
                if (!await okToReload()) return;
                run('Deleting…', function () {
                    return sendContent({ action: 'delete_department', dept_id: DD.dept_id }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Department deleted.', { page: 'college-departments', sub: '' });
                    });
                });
            } } }, [icon('trash'), ' Delete this department']);
            var headerControls = [];
            if (DD.kind === 'college' && TD) {
                headerControls.push(h('small', { class: 've-pop-hint', text: 'Header changes apply only here. Publish to save them.' }));
                ['text', 'logo'].forEach(function (part) {
                    headerControls.push(h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary',
                        text: (headerHidden('header_' + part + '_hidden') ? 'Restore' : 'Remove') + ' header ' + part,
                        on: { click: function () { toggleHeaderPart(part); } } }));
                });
            }
            openPop(h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'Department options' }),
                h('small', { class: 've-pop-hint', text: hidden ? 'This department is hidden from visitors.' : 'This department is showing on the website.' })].concat(headerControls, [toggle, del])), x, y);
        }
        function saveDept() {
            var errs = [], did = 0, p = Promise.resolve();
            if (departmentOrder && departmentOrder.dirty()) p = p.then(function(){
                return sendContent({action:'save_department_order',scope:departmentOrder.scope,order:JSON.stringify(departmentOrder.order())}).then(function(r){
                    if(r.ok){did++;departmentOrder.saved();}else errs.push('Department order: '+r.error);
                });
            });
            var cards = Object.keys(departmentPrograms).filter(programCardDirty);
            if (cards.some(function (key) { return !departmentPrograms[key].program_name.trim(); })) {
                return Promise.resolve({ did: 0, errs: ['Add a heading to each new program card before publishing.'] });
            }
            cards.forEach(function (key) {
                p = p.then(function () {
                    var row = departmentPrograms[key];
                    return send(PROG_URL, { action: 'save_department_program_card', dept_id: DD.dept_id, program_id: row.program_id,
                        program_name: row.program_name, description: row.description, remove_image: row.removeImage ? '1' : '' }, row.file ? { image: row.file } : {}).then(function (response) {
                        if (response.ok) {
                            did++; row.program_id = response.program.program_id;
                            row.baseName = row.program_name; row.baseDescription = row.description;
                            row.hasImage = !!row.file || (row.hasImage && !row.removeImage);
                            row.file = null; row.removeImage = false; row.added = false;
                        } else errs.push('Program card: ' + response.error);
                    });
                });
            });
            if (TD && Object.keys(textChanges).length) p = p.then(function () {
                return sendContent({ action: 'save_department_page_text', dept_id: TD.dept_id, texts: JSON.stringify(textChanges) }).then(function (response) {
                    if (response.ok) {
                        did++; textChanges = {};
                        if (response.fields) TD.fields = response.fields;
                    } else errs.push('Page text: ' + response.error);
                });
            });
            if (BD && (Object.keys(bChange).length || Object.keys(bFiles).length)) p = p.then(function () {
                return sendContent({ action: 'save_basic_education_content', page: BD.page, texts: JSON.stringify(bChange) }, bFiles).then(function (r) {
                    if (r.ok) {
                        did++; Object.keys(bChange).forEach(function (key) { BD.fields[key].value = bChange[key]; });
                        bChange = {}; bFiles = {};
                    }
                    else errs.push((BD.page === 'college-index' ? 'College Departments' : 'Basic Education') + ': ' + r.error);
                });
            });
            Object.keys(bLogos).forEach(function (id) {
                p = p.then(function () {
                    return sendContent({ action: 'save_department_media', dept_id: id }, { logo: bLogos[id] }).then(function (r) {
                        if (r.ok) { did++; delete bLogos[id]; }
                        else errs.push('Department logo: ' + r.error);
                    });
                });
            });
            var deptKeys = DEPT_KEYS.filter(function (k) { return own(dChange, k); });
            var deanKeys = DEAN_KEYS.filter(function (k) { return own(dChange, k); });
            if (deptKeys.length) p = p.then(function () {
                return sendContent({ action: 'save_department_details', dept_id: DD.dept_id, dept_name: dVal('dept_name'), description: dBase('description'), hero_text: dVal('hero_text'),
                    about_text: dVal('about_text'), facebook_url: dVal('facebook_url'), office_label: dVal('office_label'), theme_color: dBase('theme_color') }).then(function (r) {
                    if (r.ok) { did++; deptKeys.forEach(function (k) { delete dChange[k]; }); if (r.department) Object.keys(r.department).forEach(function (k) { DD[k] = r.department[k]; }); }
                    else errs.push('Department: ' + r.error);
                });
            });
            if (deanKeys.length || dFiles.photo || dRemove.photo) p = p.then(function () {
                var f = { action: 'save_department_dean', dept_id: DD.dept_id };
                DEAN_KEYS.forEach(function (k) { f[k] = dVal(k); });
                if (dRemove.photo && !dFiles.photo) f.remove_photo = '1';
                return sendContent(f, dFiles.photo ? { dean_photo: dFiles.photo } : {}).then(function (r) {
                    if (r.ok) { did++; deanKeys.forEach(function (k) { delete dChange[k]; }); delete dFiles.photo; delete dRemove.photo; if (r.department) Object.keys(r.department).forEach(function (k) { DD[k] = r.department[k]; }); }
                    else errs.push('Head of Office: ' + r.error);
                });
            });
            if (dFiles.banner || dRemove.banner) p = p.then(function () {
                var f = { action: 'save_department_banner', dept_id: DD.dept_id };
                if (dRemove.banner && !dFiles.banner) f.remove_banner = '1';
                return sendContent(f, dFiles.banner ? { banner: dFiles.banner } : {}).then(function (r) {
                    if (r.ok) { did++; delete dFiles.banner; delete dRemove.banner; if (r.department) Object.keys(r.department).forEach(function (k) { DD[k] = r.department[k]; }); }
                    else errs.push('Department banner: ' + r.error);
                });
            });
            if (dFiles.logo || dRemove.logo) p = p.then(function () {
                var f = { action: 'save_department_media', dept_id: DD.dept_id };
                if (dRemove.logo && !dFiles.logo) f.remove_logo = '1';
                return sendContent(f, dFiles.logo ? { logo: dFiles.logo } : {}).then(function (r) {
                    if (r.ok) { did++; delete dFiles.logo; delete dRemove.logo; if (r.department) Object.keys(r.department).forEach(function (k) { DD[k] = r.department[k]; }); }
                    else errs.push('Logo: ' + r.error);
                });
            });
            if (officeChange && DD) p = p.then(function () {
                return sendContent({ action: 'save_department_office_location', dept_id: DD.dept_id, location: JSON.stringify(officeChange) }).then(function (r) {
                    if (r.ok) {
                        did++; officeChange = null;
                        if (r.department) Object.keys(r.department).forEach(function (key) { DD[key] = r.department[key]; });
                    } else errs.push('Office location: ' + r.error);
                });
            });
            return p.then(function () { return { did: did, errs: errs }; });
        }

        // ── programs ──
        function shortName(n) { var s = String(n).replace(/^Bachelor of (?:Science|Arts)?\s*(?:in\s+)?/i, ''); return s !== '' ? s : String(n); }
        function pMetaSet(k, v, base) { if (String(v) === String(base)) delete pMeta[k]; else pMeta[k] = v; }
        function metaVal(k) { return own(pMeta, k) ? pMeta[k] : (k === 'name' ? PD.name : k === 'description' ? PD.description : k === 'dept_id' ? PD.dept_id : k === 'theme' ? PD.theme : k === 'theme_custom' ? PD.theme_custom : k === 'core_enabled' ? PD.core_enabled : PD.published); }
        function postItems(type, rows) {
            var fields = { action: 'save_program_items', program_id: PD.program_id, item_type: type,
                items: JSON.stringify(rows.map(function (r) { return { item_id: r.item_id || 0, title: r.title || '', subtitle: r.subtitle || '', body: r.body || '', icon: r.icon || '', letter: r.letter || '', remove_image: !!r.removeImg }; })) };
            var files = {};
            rows.forEach(function (r, n) { if (r.file) files['file_' + n] = r.file; });
            return send(PROG_URL, fields, files);
        }
        /** Adding, moving and deleting a card save straight away (the order and number of cards change the page), then the page refreshes. */
        async function structural(type, rows, msg) {
            if (!await okToReload(rowsChanged(type) ? 1 : 0)) return;
            run('Saving…', function () {
                return postItems(type, rows).then(function (r) {
                    if (!r.ok) { toast(r.error, 'error'); return; }
                    reloadEditor(msg, routeNow());
                });
            });
        }
        function addItem(type) {
            if (!PD || !PD.items[type]) return;
            var rows = cardRowsOf(type).slice();
            var lim = (PD.limits && PD.limits[type]) || 12;
            if (rows.length >= lim) { toast('This list is full (' + lim + '). Delete one to add another.', 'error'); return; }
            rows.push({ item_id: 0, title: ITEM_NEW[type] || 'New', subtitle: '', body: '', icon: '', letter: '' });
            structural(type, rows, 'Added. Click the new card to write it.');
        }
        function moveItem(r, d) {
            var rows = cardRowsOf(r.type).slice(), j = r.i + d;
            if (j < 0 || j >= rows.length) return;
            var m = rows.splice(r.i, 1)[0]; rows.splice(j, 0, m);
            structural(r.type, rows, 'Order saved.');
        }
        async function deleteItem(r) {
            var rows = cardRowsOf(r.type).slice(), row = rows[r.i];
            if (!await window.AdminDialogs.confirm('Delete “' + (row && row.title || 'this card') + '” for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
            rows.splice(r.i, 1);
            structural(r.type, rows, 'Deleted.');
        }
        /** The editor page itself does not load the Material Symbols font (only the preview does), so load it here and style the picker. */
        function itemIcon(r) {
            var cardEl = S.sel && S.sel.el ? (S.sel.el.closest('[data-ve-item]') || S.sel.el) : null, row = cardRowsOf(r.type)[r.i];
            if (!row || !cardEl) return;
            var apply = function (spec) {
                if (!/^(material:[a-z0-9_]+|bi:[a-z0-9-]+)$/i.test(spec)) { toast('Use a name like material:school or bi:mortarboard.', 'error'); return; }
                row.icon = spec; pruneRows();
                var ic = cardEl.querySelector('.material-symbols-outlined, i.bi');
                if (ic) {
                    var extra = Array.prototype.slice.call(ic.classList).filter(function (c) { return c !== 'material-symbols-outlined' && c !== 'bi' && !/^bi-/.test(c); }).join(' ');
                    var parts = spec.split(':'), nm = parts[1];
                    if (parts[0] === 'material') { ic.className = 'material-symbols-outlined ' + extra; ic.textContent = nm; }
                    else { ic.className = 'bi bi-' + nm + ' ' + extra; ic.textContent = ''; }
                }
                closePop(); renderToolbar(); chrome();
            };
            libraryIconPicker(PD.icons || {}, row.icon || '', apply);
        }
        function itemPicture(r, remove) {
            var cardEl = S.sel && S.sel.el ? (S.sel.el.closest('[data-ve-item]') || S.sel.el) : null, row = cardRowsOf(r.type)[r.i];
            if (!row || !cardEl) return;
            var bg = cardEl.querySelector('.career-panel-bg');
            if (remove) {
                row.removeImg = true; row.file = null; if (bg) bg.style.backgroundImage = 'none'; cardEl.classList.add('career-panel--plain');
                renderToolbar(); chrome(); return;
            }
            pickFile('image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp', function (f) {
                if (f.size > 5 * MB) { toast('That picture is over 5 MB. Please choose a smaller one.', 'error'); return; }
                row.file = f; row.removeImg = false;
                if (bg) bg.style.backgroundImage = 'url("' + previewUrl(f) + '")';
                cardEl.classList.remove('career-panel--plain'); renderToolbar(); chrome();
            });
        }
        function itemActions(it, ref) {
            var r = itemRef(ref); if (!r) return;
            var n = curRows(r.type).length, row = curRows(r.type)[r.i] || {};
            it.push({ type: 'sep' });
            it.push({ type: 'chip', text: (ITEM_LABEL[r.type] || 'Card') + ' ' + (r.i + 1) + ' of ' + n });
            if (ITEM_ICON[r.type]) it.push({ icon: 'grid-3x3-gap', label: 'Icon', title: 'Change the icon', run: function () { itemIcon(r); } });
            if (ITEM_IMAGE[r.type]) {
                var hasImg = (!!row.image_url && !row.removeImg) || !!row.file;
                it.push({ icon: 'image', label: hasImg ? 'Picture' : 'Add picture', title: 'Choose a picture for this card', run: function () { itemPicture(r); } });
                if (hasImg) it.push({ icon: 'image-alt', title: 'Remove the picture', run: function () { itemPicture(r, true); } });
            }
            it.push({ icon: 'arrow-left', title: 'Move earlier', disabled: r.i === 0, run: function () { moveItem(r, -1); } });
            it.push({ icon: 'arrow-right', title: 'Move later', disabled: r.i === n - 1, run: function () { moveItem(r, 1); } });
            it.push({ icon: 'trash', title: 'Delete this card', danger: true, run: function () { deleteItem(r); } });
        }
        function curriculumOptions(x,y) {
            if(!PD)return;
            var box=h('div',{class:'ve-pop-form'},[h('strong',{class:'ve-pop-label',text:'Curriculum PDF'}),h('small',{class:'ve-pop-hint',text:pPdf?(pPdf.file?pPdf.file.name+' · not published':'Removal pending'):PD.pdf_name||'No curriculum PDF uploaded'})]);
            box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Upload or replace PDF',on:{click:function(){
                pickFile('application/pdf,.pdf',function(file){
                    if(!/\.pdf$/i.test(file.name)||file.size>20*MB){toast('Choose a PDF of up to 20 MB.','error');return;}
                    pPdf={file:file};closePop();chrome();toast('PDF ready. Save draft or Publish when ready.','ok');
                });
            }}}));
            if(PD.pdf_url||(pPdf&&pPdf.file))box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-outline-danger',text:'Remove PDF',on:{click:function(){pPdf=PD.pdf_url?{remove:true}:null;closePop();chrome();}}}));
            if(pPdf)box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Undo PDF change',on:{click:function(){pPdf=null;closePop();chrome();}}}));
            box.appendChild(h('small',{class:'ve-pop-hint',text:'Changes are private until you publish.'}));
            openPop(box,x,y);
        }
        function programMedia(key) {
            pickFile(key==='seal'?'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,.svg':'image/jpeg,image/png,image/webp,image/gif',function(file){
                if(file.size>5*MB){toast('Choose an image of up to 5 MB.','error');return;}
                pMedia[key]={file:file};
                var image=key==='seal'?S.doc.querySelector('.about-feature-seal-wrap img'):null;
                if(image)image.src=previewUrl(file);
                closePop();chrome();toast('Picture ready. Save draft or Publish when ready.','ok');
            });
        }
        function progOptions(x, y) {
            if (!PD) return;
            var box = h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'Program options' })]);
            var sw = function (label, key, base, hint) {
                var cb = h('input', { type: 'checkbox', class: 'form-check-input' }); cb.checked = !!metaVal(key);
                cb.addEventListener('change', function () { pMetaSet(key, cb.checked, base); chrome(); });
                return h('label', { class: 've-switch' }, [cb, h('span', {}, [label, hint ? h('small', { class: 've-pop-hint', text: hint }) : null])]);
            };
            box.appendChild(sw('Show this program on the website', 'published', PD.published, 'Turn off to hide the whole page from visitors.'));
            box.appendChild(sw('Show the Core Values section', 'core_enabled', PD.core_enabled));
            var department=h('select',{class:'form-select form-select-sm','aria-label':'Program department'});
            (C.programDepartments||[]).forEach(function(unit){department.appendChild(h('option',{value:unit.id,text:unit.name}));});department.value=String(metaVal('dept_id'));
            department.addEventListener('change',function(){pMetaSet('dept_id',Number(department.value),PD.dept_id);chrome();});
            box.appendChild(h('label',{class:'ve-pop-label',text:'Department'},[department]));
            box.appendChild(h('div',{class:'ve-pop-row'},[
                h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Replace program seal',on:{click:function(){programMedia('seal');}}}),
                h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Replace fallback banner',on:{click:function(){programMedia('hero');}}})
            ]));
            ['seal','hero'].forEach(function(key){if(PD[key+'_upload']||(pMedia[key]&&pMedia[key].file))box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Use default '+(key==='seal'?'seal':'banner'),on:{click:function(){pMedia[key]={remove:true};closePop();chrome();}}}));});
            var cur = metaVal('theme'), curHex = metaVal('theme_custom');
            var sws = h('div', { class: 've-swatches' });
            Object.keys(PD.presets).forEach(function (k) {
                var b = h('button', { type: 'button', class: 've-sw' + (cur === k ? ' is-on' : ''), title: PD.presets[k].label, style: 'background:' + PD.presets[k].accent, on: { click: function () {
                    pMetaSet('theme', k, PD.theme); pMetaSet('theme_custom', PD.presets[k].accent, PD.theme_custom);
                    Array.prototype.forEach.call(sws.children, function (c) { c.classList.toggle('is-on', c === b); }); chrome();
                } } });
                sws.appendChild(b);
            });
            var color = h('input', { type: 'color', class: 've-color', title: 'Pick any colour', value: /^#[0-9a-f]{6}$/i.test(curHex) ? curHex : '#307056' });
            color.addEventListener('input', function () {
                pMetaSet('theme', 'custom', PD.theme); pMetaSet('theme_custom', color.value.toUpperCase(), PD.theme_custom);
                Array.prototype.forEach.call(sws.children, function (c) { c.classList.remove('is-on'); }); chrome();
            });
            box.appendChild(h('label', { class: 've-pop-label', text: 'Page colour' }));
            box.appendChild(h('div', { class: 've-pop-row' }, [sws, color]));
            box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Manage curriculum PDF',on:{click:function(){curriculumOptions(x,y);}}}));
            box.appendChild(h('small', { class: 've-pop-hint', text: 'These apply when you press Publish.' }));
            box.appendChild(h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: async function () {
                closePop();
                if (!await window.AdminDialogs.confirm('Delete “' + PD.name + '” for good?\n\nThe whole page is removed from the website straight away. This cannot be undone.')) return;
                if (!await okToReload()) return;
                run('Deleting…', function () {
                    return send(PROG_URL, { action: 'delete_program', program_id: PD.program_id }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Program deleted.', { page: 'programs', sub: '' });
                    });
                });
            } } }, [icon('trash'), ' Delete this program']));
            openPop(box, x, y);
        }
        function saveProg() {
            var errs = [], did = 0, p = Promise.resolve();
            Object.keys(LISTS).forEach(function(key){
                var L=LISTS[key];if(!L.data)return;
                var rows=rowsOf(L.data.slides),changes=[];
                rows.forEach(function(row){var id=replacementKey(L,row);if(slideReplacements[id]){row.file=slideReplacements[id].file;changes.push(id);}});
                if(!changes.length)return;
                p=p.then(function(){return postRows(L,rows).then(function(result){
                    if(!result.ok){errs.push(L.label+': '+result.error);return;}
                    did++;afterSave(L,result);changes.forEach(function(id){delete slideReplacements[id];});
                    if(result.items)L.data.slides=result.items;
                });});
            });
            var details = Object.keys(pChange).length > 0 || Object.keys(pMeta).length > 0 || Object.keys(pMedia).length > 0;
            if (details) p = p.then(function () {
                var f = { action: 'save_program_details', program_id: PD.program_id, program_name: metaVal('name'), description: metaVal('description'), dept_id: metaVal('dept_id'),
                    theme: metaVal('theme'), theme_custom: metaVal('theme_custom'), core_values_enabled: metaVal('core_enabled') ? '1' : '', is_published: metaVal('published') ? '1' : '' };
                Object.keys(PD.fields).forEach(function (k) { f['c_' + k] = own(pChange, k) ? pChange[k] : pBase(k); });
                var files={};Object.keys(pMedia).forEach(function(key){if(pMedia[key].file)files[key]=pMedia[key].file;else f['remove_'+key]='1';});
                return send(PROG_URL, f, files).then(function (r) {
                    if (r.ok) {
                        did++;
                        Object.keys(pChange).forEach(function (k) { PD.content[k] = pChange[k]; });
                        ['name', 'description', 'dept_id', 'theme', 'theme_custom', 'core_enabled', 'published'].forEach(function (k) { if (own(pMeta, k)) PD[k] = pMeta[k]; });
                        pChange = {}; pMeta = {};pMedia={};
                    } else errs.push('Page text: ' + r.error);
                });
            });
            Object.keys(pRows).filter(rowsChanged).forEach(function (type) {
                p = p.then(function () {
                    return postItems(type, pRows[type]).then(function (r) {
                        if (r.ok) { did++; delete pRows[type]; } else errs.push((ITEM_LABEL[type] || 'Cards') + ' list: ' + r.error);
                    });
                });
            });
            if(pPdf)p=p.then(function(){return send(PROG_URL,{action:'save_program_pdf',program_id:PD.program_id,remove_pdf:pPdf.remove?'1':''},pPdf.file?{pdf:pPdf.file}:{}).then(function(result){
                if(result.ok){did++;pPdf=null;}else errs.push('Curriculum PDF: '+result.error);
            });});
            return p.then(function () { return { did: did, errs: errs }; });
        }

        var A = {
            draftState: function(value){
                if(!value)return {textChanges:textChanges,departmentPrograms:departmentPrograms,nextProgramDraft:nextProgramDraft,officeChange:officeChange,bChange:bChange,bFiles:bFiles,bLogos:bLogos,dChange:dChange,dFiles:dFiles,dRemove:dRemove,pChange:pChange,pMeta:pMeta,pRows:pRows,slideReplacements:slideReplacements,pPdf:pPdf,pMedia:pMedia,departmentOrder:departmentOrder&&departmentOrder.order()};
                textChanges=value.textChanges;departmentPrograms=value.departmentPrograms;nextProgramDraft=value.nextProgramDraft;officeChange=value.officeChange;bChange=value.bChange;bFiles=value.bFiles;bLogos=value.bLogos;dChange=value.dChange;dFiles=value.dFiles;dRemove=value.dRemove;pChange=value.pChange;pMeta=value.pMeta;pRows=value.pRows;slideReplacements=value.slideReplacements||{};pPdf=value.pPdf||null;pMedia=value.pMedia||{};
                Object.keys(departmentPrograms).forEach(function(key){if(!S.doc.querySelector('[data-ve-program-card="'+cssEsc(key)+'"]'))appendProgramCard(S.doc,false,key,departmentPrograms[key]);});
                bindEditing(S.doc);
            },
            draftRebind:function(){bindEditing(S.doc);restoreSlideReplacements();if(pMedia.seal&&pMedia.seal.file){var seal=S.doc.querySelector('.about-feature-seal-wrap img');if(seal)seal.src=previewUrl(pMedia.seal.file);}},
            mount: function (doc) {
                if (departmentOrder) { departmentOrder.dispose(); departmentOrder=null; }
                resetPending();
                LISTS.banner.data = readData(doc, 'veBannerData');
                LISTS.facility.data = readData(doc, 'veFacData');
                DD = readData(doc, 'veDeptData'); PD = readData(doc, 'veProgData');
                LISTS.uniform.data = PD ? {program_id:PD.program_id,limit:PD.limits.uniform,slides:PD.items.uniform || []} : null;
                TD = readData(doc, 'veDeptPageData');
                BD = readData(doc, 'veBasicData');
                var programCards = readData(doc, 'veDeptProgramsData');
                if (DD && !BD && Array.isArray(programCards)) {
                    programCards.forEach(function (card) {
                        departmentPrograms[String(card.program_id)] = { program_id: card.program_id, program_name: card.program_name, description: card.description || '',
                            baseName: card.program_name, baseDescription: card.description || '', hasImage: !!card.image_url };
                        var element = doc.querySelector('[data-ve-program-card="' + card.program_id + '"]');
                        if (element) departmentPrograms[String(card.program_id)].url = element.getAttribute('data-ve-goto');
                    });
                    if (!programCards.length) appendProgramCard(doc, false);
                }
                mode = DD ? 'dept' : PD ? 'prog' : BD ? 'basic' : (doc.querySelector('[data-ve-add="department"]') ? 'deptlist' : '');
                var addProgramBtn = $('#veAddProgramBtn');
                if (addProgramBtn && !addProgramBtn.__veBound) {
                    addProgramBtn.__veBound = true;
                    addProgramBtn.addEventListener('click', function () { addProgram(addProgramBtn); });
                }
                if (DD || PD || BD) bindEditing(doc);
                if (window.GNCDepartmentOrder) departmentOrder=window.GNCDepartmentOrder.create(doc,{
                    state:function(){return {preview:S.preview,busy:S.busy};},
                    start:function(){select(null);closePop();},changed:function(){chrome();},announce:function(message){toast(message,'ok');}
                });
                renderStrip();
                syncSub();
                return false;
            },
            pick: function (t) {
                if (!t || !t.closest) return null;
                var programSection = t.closest('[data-ve-program-section]');
                if (programSection && PD) {
                    var sectionKey = programSection.getAttribute('data-ve-program-section');
                    if (sectionKey === 'uniform' || sectionKey === 'curriculum') {
                        if(sectionKey==='uniform'){tab='uniform';renderStrip();}
                        else {var rect=programSection.getBoundingClientRect(),frameRect=S.frame.getBoundingClientRect();curriculumOptions(rect.left+frameRect.left,rect.top+frameRect.top);}
                        return { kind: 'none' };
                    }
                }
                if (t.closest('[data-ve-department-drag]')) return {kind:'none'};
                var fe = fieldOf(t);
                if (fe) {
                    if (!fe.__ve) return null;
                    var it0 = fe.getAttribute('data-ve-field') === 'prog-item' ? fe.closest('[data-ve-item]') : null;
                    return { kind: 'field', el: fe, item: it0 ? it0.getAttribute('data-ve-item') : null, prevent: false };
                }
                var statementIcon = t.closest('[data-ve-icon="department-statement"],[data-ve-icon="program-statement"]');
                if (statementIcon) {
                    var statementKey = statementIcon.getAttribute('data-ve-key');
                    var programStatement = statementIcon.getAttribute('data-ve-icon') === 'program-statement';
                    if (/^(vision|mission)_icon$/.test(statementKey) && (programStatement ? PD : TD)) {
                        return { kind: programStatement ? 'program-statement-icon' : 'department-statement-icon', el: statementIcon, key: statementKey, prevent: true };
                    }
                }
                var academicIcon = t.closest('[data-ve-icon="academic-feature"]');
                if (academicIcon && BD) return { kind: 'academic-icon', el: academicIcon, key: academicIcon.getAttribute('data-ve-key'), prevent: true };
                var basicImage = t.closest('[data-ve-basic-image]');
                var programImage = t.closest('[data-ve-program-image]');
                if (programImage && DD) return { kind: 'department-program-image', el: programImage, key: programImage.getAttribute('data-ve-program-image'), prevent: true };
                if (basicImage && BD) return { kind: 'basic-image', el: basicImage, key: basicImage.getAttribute('data-ve-basic-image'), prevent: true };
                var basicLogo = t.closest('[data-ve-basic-logo-id]');
                if (basicLogo && BD) return { kind: 'basic-logo', el: basicLogo, id: +basicLogo.getAttribute('data-ve-basic-logo-id'), prevent: true };
                var img = t.closest('[data-ve-img]');
                if (img && DD) { var w = img.getAttribute('data-ve-img'); if (w === 'dept-logo' || w === 'dean-photo' || w === 'dept-banner') return { kind: 'dimg', el: img, which: w, prevent: true }; }
                if (PD && !t.closest('a,button:not(.fcar-slide),[data-ve-pick],[data-ve-add],[data-ve-icon],.hero-dots,.fcar-controls')) {
                    var seal = t.closest('.about-feature-seal-wrap');
                    if (seal) return {kind:'program-media',el:seal,key:'seal',prevent:true};
                    var picture = t.closest('.fcar-slide,.template-uniform-card img,.template-hero.has-banner');
                    if (picture && !(picture.matches('.template-hero') && t.closest('[data-ve-item]'))) {
                        var listKey = picture.matches('.template-hero') ? 'banner' : picture.matches('.fcar-slide') ? 'facility' : 'uniform';
                        var selector = listKey === 'banner' ? '.hero-slide' : listKey === 'facility' ? '.fcar-slide' : '.template-uniform-card img';
                        var active = listKey === 'banner' ? picture.querySelector('.hero-slide.is-active') : picture;
                        var index = Array.prototype.indexOf.call(S.doc.querySelectorAll(selector),active);
                        if (index >= 0 && LISTS[listKey].data && LISTS[listKey].data.slides[index]) return {kind:'program-slide',el:picture,listKey:listKey,index:index,prevent:true,stop:true};
                    }
                }
                var pk = t.closest('[data-ve-pick]');
                if (pk) {
                    var pv = pk.getAttribute('data-ve-pick');
                    if (pv === 'office-location' && DD) { editOfficeLocation(pk); return { kind: 'none' }; }
                    if (pv === 'dept-facebook' && DD) { editFacebook(); return { kind: 'none' }; }
                }
                var add = t.closest('[data-ve-add]');
                if (add) {
                    var ak = add.getAttribute('data-ve-add');
                    if (ak === 'department') { addDepartment(add); return { kind: 'none' }; }
                    if (ak === 'program' && DD) { addProgram(add); return { kind: 'none' }; }
                    if (ak === 'item' && PD) { addItem(add.getAttribute('data-ve-type')); return { kind: 'none' }; }
                }
                var card = t.closest('[data-ve-item]');
                if (card && PD) return { kind: 'card', el: card, item: card.getAttribute('data-ve-item'), prevent: true };
                var go = t.closest('[data-ve-goto]');
                if (go) return { kind: 'goto', el: go, url: go.getAttribute('data-ve-goto'), programId: +go.getAttribute('data-ve-program-id'), label: go.getAttribute('data-ve-label') || '', prevent: true };
                var programCard = t.closest('[data-ve-program-card]');
                if (programCard && DD) return { kind: 'department-program-card', el: programCard, key: programCard.getAttribute('data-ve-program-card'), prevent: true };
                return null;
            },
            toolbar: function (sel) {
                var it = [];
                if (sel.kind === 'program-slide') {
                    it.push({icon:'image',label:'Replace picture',primary:true,run:function(){replaceSlide(LISTS[sel.listKey],sel.index);}});
                } else if (sel.kind === 'program-media') {
                    it.push({icon:'image',label:'Replace picture',primary:true,run:function(){programMedia(sel.key);}});
                } else if (sel.kind === 'field') {
                    var v = sel.el.__ve || {}, len = readField(sel.el).length;
                    it.push({ type: 'chip', icon: 'pencil', text: (v.label || 'Text') + (v.max ? '  ·  ' + len + ' / ' + v.max : '') });
                    if (TD && sel.el.closest('[data-dept-header-text]')) it.push({ icon: 'trash', label: 'Remove header text', danger: true, run: function () { toggleHeaderPart('text'); } });
                    if (v.overview) return richTextTools().concat(it);
                    if (sel.item) itemActions(it, sel.item);
                    if (/^dept-program-(name|description)$/.test(sel.el.getAttribute('data-ve-field') || '')) departmentProgramActions(it, sel.el.getAttribute('data-ve-key'));
                } else if (sel.kind === 'department-program-card') {
                    it.push({ type: 'chip', icon: 'mortarboard', text: 'Click the image or text to edit' });
                    departmentProgramActions(it, sel.key);
                } else if (sel.kind === 'department-program-image') {
                    var programRow = departmentPrograms[sel.key];
                    if (!programRow) return it;
                    it.push({ icon: 'image', label: programRow.hasImage || programRow.file ? 'Replace picture' : 'Add image', primary: true, run: function () {
                        pickFile('image/jpeg,image/png,image/webp,image/gif', function (file) {
                            if (file.size > 5 * MB) { toast('That picture is over 5 MB.', 'error'); return; }
                            programRow.file = file; programRow.removeImage = false; showPic(sel.el, previewUrl(file)); renderToolbar(); chrome();
                        });
                    } });
                    departmentProgramActions(it, sel.key);
                } else if (sel.kind === 'card') {
                    var r0 = itemRef(sel.item);
                    it.push({ type: 'chip', icon: 'card-text', text: 'Click the text to edit it' });
                    itemActions(it, sel.item);
                    if (!r0) it.length = 0;
                } else if (sel.kind === 'program-statement-icon' || sel.kind === 'department-statement-icon') {
                    it.push({ icon: 'grid-3x3-gap', label: 'Change icon', primary: true, run: function () { statementIconPicker(sel); } });
                } else if (sel.kind === 'academic-icon') {
                    it.push({ icon: 'grid-3x3-gap', label: 'Change icon', primary: true, run: function () { academicIconPicker(sel); } });
                } else if (sel.kind === 'basic-image') {
                    it.push({ icon: 'image', label: 'Replace picture', primary: true, run: function () {
                        pickFile('image/jpeg,image/png,image/webp,image/gif', function (file) {
                            if (file.size > 5 * MB) { toast('That picture is over 5 MB.', 'error'); return; }
                            bFiles[sel.key] = file; showPic(sel.el, previewUrl(file)); renderToolbar(); chrome();
                        });
                    } });
                } else if (sel.kind === 'basic-logo') {
                    it.push({ icon: 'image', label: 'Replace logo', primary: true, run: function () {
                        if (!sel.id) { toast('This department is not available in the editor.', 'error'); return; }
                        pickFile('image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg', function (file) {
                            if (file.size > 5 * MB) { toast('That logo is over 5 MB.', 'error'); return; }
                            bLogos[sel.id] = file; showPic(sel.el, previewUrl(file)); renderToolbar(); chrome();
                        });
                    } });
                } else if (sel.kind === 'dimg') {
                    var logo = sel.which === 'dept-logo', banner = sel.which === 'dept-banner', key = banner ? 'banner' : (logo ? 'logo' : 'photo');
                    var has = (!!(banner ? DD.banner_url : (logo ? DD.logo_url : DD.dean_photo_url)) && !dRemove[key]) || !!dFiles[key];
                    it.push({ type: 'chip', icon: 'image', text: banner ? 'Header background' : (logo ? 'Department logo' : 'Head of Office photo') });
                    it.push({ icon: 'image', label: has ? 'Replace' : 'Add picture', primary: true, run: function () {
                        pickFile(logo ? 'image/*' : 'image/jpeg,image/png,image/webp', function (f) {
                            if (f.size > 5 * MB) { toast('That picture is over 5 MB.', 'error'); return; }
                            dFiles[key] = f; delete dRemove[key];
                            if (banner) {
                                var layer = sel.el.ownerDocument.querySelector('[data-dept-banner-image]');
                                var image = layer && layer.querySelector('img');
                                if (image) { image.hidden = false; showPic(layer, previewUrl(f)); }
                            } else if (logo) {
                                Array.prototype.forEach.call(sel.el.ownerDocument.querySelectorAll('[data-ve-img="dept-logo"]'), function (el) { showPic(el, previewUrl(f)); });
                            } else showPic(sel.el, previewUrl(f));
                            renderToolbar(); chrome();
                        }, !logo && !banner, banner ? sel.el.ownerDocument.querySelector('[data-dept-banner-image]') : sel.el);
                    } });
                    if (logo && sel.el.closest('[data-dept-header-logo]')) it.push({ icon: 'trash', label: 'Remove header logo', danger: true, run: function () { toggleHeaderPart('logo'); } });
                    else if (has) it.push({ icon: 'trash', title: 'Remove the picture', danger: true, run: function () {
                        dRemove[key] = true; delete dFiles[key];
                        if (banner) {
                            var image = sel.el.ownerDocument.querySelector('[data-dept-banner-image] img');
                            if (image) { image.hidden = true; image.removeAttribute('src'); }
                        } else sel.el.style.opacity = '.25';
                        renderToolbar(); chrome();
                    } });
                    if (dFiles[key]) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'New picture, not saved yet' });
                    if (dRemove[key]) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Will be removed' });
                } else if (sel.kind === 'goto') {
                    it.push({ type: 'chip', icon: 'mortarboard', text: clip(sel.label || 'Program', 40) });
                    it.push({ icon: 'pencil-square', label: 'Edit this program', primary: true, title: 'Open this program’s page to edit it', run: function () { openPage(sel.url); } });
                    if (sel.programId > 0) it.push({ icon: 'trash', label: 'Delete program', title: 'Delete this program', danger: true, run: async function () {
                        if (!await window.AdminDialogs.confirm('Delete “' + (sel.label || 'this program') + '” for good?\n\nThe whole program page is removed from the website immediately. This cannot be undone.')) return;
                        if (!await okToReload()) return;
                        run('Deleting program…', function () {
                            return send(PROG_URL, { action: 'delete_program', program_id: sel.programId }).then(function (r) {
                                if (!r.ok) { toast(r.error, 'error'); return; }
                                reloadEditor('Program deleted.', routeNow());
                            });
                        });
                    } });
                }
                return it;
            },
            onLink: function (a) {
                var u; try { u = new URL(a.getAttribute('href') || '', S.win.location.href); } catch (e) { return false; }
                if (u.origin !== window.location.origin || u.pathname.indexOf('/pages/academics/') !== 0) return false;
                openPage(u.pathname + u.search); return true;
            },
            dirtyCount: function () { return pendingCount(); },
            consumeClick: function () { return departmentOrder && departmentOrder.consumeClick(); },
            hasPageOptions: function () { return (mode === 'dept' && DD && DD.kind === 'college') || mode === 'prog'; },
            pageOptions: function (x, y) { if (mode === 'dept') deptOptions(x, y); else if (mode === 'prog') progOptions(x, y); },
            status: function () {
                var n = pendingCount();
                if (n) return { text: plural(n, 'unsaved change'), tone: 'warn' };
                if (mode === 'dept') return DD.archived ? { text: 'Hidden from the website', tone: 'warn' } : { text: 'Everything here is live', tone: 'ok' };
                if (mode === 'prog') return !PD.published ? { text: 'Hidden from the website', tone: 'warn' } : { text: 'Everything here is live', tone: 'ok' };
                return { text: 'Click a department or program to open it, then click any text or picture to edit it.', tone: 'ok' };
            },
            actions: function () {
                if (departmentOrder || mode === 'dept' || mode === 'prog' || mode === 'basic') return { secondary: null, primary: { label: 'Publish', icon: 'cloud-upload', disabled: pendingCount() === 0, title: 'Your changes go live on the website as soon as you publish.' } };
                return { secondary: null, primary: C.hasDrawer ? { label: 'More settings', icon: 'sliders', title: 'Open the extra settings for this page' } : null };
            },
            runPrimary: function () {
                if (!departmentOrder && mode !== 'dept' && mode !== 'prog' && mode !== 'basic') { openDrawer(); return; }
                if (!pendingCount()) return;
                confirmPublish('Publish your changes to the live website?', function () {
                    run('Publishing…', function () {
                        return (mode === 'prog' ? saveProg() : saveDept()).then(function (r) {
                            r.errs.forEach(function (m) { toast(m, 'error'); });
                            if (r.did && !r.errs.length) reloadEditor('Published. Your changes are live now.', routeNow(), true);
                            else if (r.did) toast('Some changes were saved. Fix the problem above and publish again.', 'ok');
                        });
                    });
                });
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: News, announcements & events (the homepage feed; the existing editors open in the side panel) ══════════════════
    function AchievementAdapter() {
        var data=null, records={}, serial=0;
        function photoEntries(row){return (row.photos||[]).map(function(photo){return Object.assign({},photo,{file:null,remove:false});});}
        function entry(row){return {base:row,changes:{},file:null,thumbnail:null,photos:photoEntries(row),removed:false};}
        function changed(rec){return !rec.base.achievement_id||rec.removed||rec.file||rec.thumbnail||Object.keys(rec.changes).length||rec.photos.some(function(photo){var old=(rec.base.photos||[]).find(function(item){return item.photo_id===photo.photo_id;});return photo.photo_id?(photo.remove||!old||photo.caption!==old.caption):!photo.remove;});}
        function dirty(){return Object.keys(records).filter(function(key){return changed(records[key]);}).length;}
        function value(rec,key){return Object.prototype.hasOwnProperty.call(rec.changes,key)?rec.changes[key]:rec.base[key];}
        function keyOf(rec){return Object.keys(records).find(function(key){return records[key]===rec;});}
        function record(el){var card=el&&el.closest('[data-ve-achievement-id]');return card&&records[card.getAttribute('data-ve-achievement-id')];}
        function nodes(rec){return $$('[data-ve-achievement-id="'+keyOf(rec)+'"]',S.doc);}
        function update(rec,key,text,source){
            if(String(text)===String(rec.base[key]==null?'':rec.base[key]))delete rec.changes[key];else rec.changes[key]=text;
            nodes(rec).forEach(function(card){$$('[data-ve-key="'+key+'"]',card).forEach(function(field){if(field!==source){field.textContent=text;markEmpty(field);}});});
            nodes(rec).forEach(function(card){var badge=$('.achievement-editor-status',card);if(badge)badge.textContent=(value(rec,'status')==='published'?'Published':'Draft')+(Number(value(rec,'is_featured'))?' · Slideshow':'');});
        }
        function bind(root){$$('[data-ve-field="achievement-field"]',root).forEach(function(field){makeField(field,function(el,text){var rec=record(el);if(rec)update(rec,el.getAttribute('data-ve-key'),text,el);});});}
        function previewImage(rec,url,kind){nodes(rec).forEach(function(card){$$('[data-ve-achievement-image]',card).forEach(function(cover){var thumb=cover.getAttribute('data-ve-achievement-image')==='thumbnail';if(kind==='thumbnail'&&!thumb&&(rec.file||rec.base.image_path))return;if(kind!=='thumbnail'&&thumb&&(rec.thumbnail||rec.base.thumbnail_path))return;var img=cover.tagName==='IMG'?cover:$('img',cover);if(!img){img=S.doc.createElement('img');cover.insertBefore(img,cover.firstChild);}img.src=url;var placeholder=$('.achievement-card-placeholder',cover);if(placeholder)placeholder.remove();});});}
        function pickImage(rec,kind,done){pickFile('image/jpeg,image/png,image/webp,image/gif',function(file){if(file.size>5*MB){toast('Use a picture of up to 5 MB.','error');return;}if(kind==='thumbnail')rec.thumbnail=file;else rec.file=file;previewImage(rec,previewUrl(file),kind);if(done)done(file);chrome();});}
        function showAchievers(rec){
            var photos=[],seen=new Set(),thumbnail=rec.thumbnail||rec.base.thumbnail_path||rec.file||rec.base.image_path;
            function include(source,caption){if(!source||source===thumbnail||seen.has(source))return;seen.add(source);photos.push({url:typeof source==='string'?source:previewUrl(source),caption:caption||''});}
            rec.photos.filter(function(photo){return !photo.remove;}).forEach(function(photo){include(photo.file||photo.image_path,photo.caption);});
            var dialog=h('dialog',{class:'achievement-modal','aria-label':value(rec,'title')||'Achievers'}), close=h('button',{type:'button',class:'achievement-modal-close','aria-label':'Close achievement',text:'×',on:{click:function(){dialog.close();}}});
            var names=String(value(rec,'achievers')||'').split(/\r?\n/).map(function(name){return name.trim();}).filter(Boolean);
            dialog.appendChild(h('div',{class:'achievement-modal-header'},[h('h2',{text:value(rec,'title')||'Achievement'}),close]));
            var body=h('div',{class:'achievement-modal-body'});
            if(names.length)body.appendChild(h('section',{class:'visually-hidden'},[h('h3',{text:'Achievers'}),h('ul',{},names.map(function(name){return h('li',{text:name});}))]));
            var stage=h('div',{class:'achievement-modal-photos',role:'region','aria-roledescription':'carousel','aria-label':'Achiever photos'},photos.map(function(photo,index){return h('figure',{class:'achievement-modal-slide','data-achiever-slide':true,hidden:index>0,role:'group','aria-roledescription':'slide','aria-label':(index+1)+' of '+photos.length},[h('img',{src:photo.url,alt:photo.caption||value(rec,'title')||'Achievers'}),photo.caption?h('figcaption',{class:'visually-hidden',text:photo.caption}):null]);}));
            if(!photos.length)stage.appendChild(h('p',{class:'achievement-modal-empty',text:'Achiever photos will be available soon.'}));
            if(photos.length>1){stage.appendChild(h('button',{type:'button',class:'achievement-modal-arrow is-previous','data-achiever-step':-1,'aria-label':'Previous achiever photo',text:'‹'}));stage.appendChild(h('button',{type:'button',class:'achievement-modal-arrow is-next','data-achiever-step':1,'aria-label':'Next achiever photo',text:'›'}));}
            body.appendChild(stage);
            if(photos.length>1)body.appendChild(h('div',{class:'achievement-modal-dots','aria-label':'Choose achiever photo'},photos.map(function(photo,index){return h('button',{type:'button',class:'achievement-modal-dot','data-achiever-index':index,'aria-label':'Show achiever photo '+(index+1),'aria-pressed':String(index===0)});})));body.appendChild(h('p',{class:'visually-hidden','data-achiever-status':true,'aria-live':'polite','aria-atomic':'true'}));
            dialog.appendChild(body);S.doc.body.appendChild(dialog);if(S.win.GNCAchievementModal)S.win.GNCAchievementModal.mount(dialog);dialog.addEventListener('click',function(event){if(event.target===dialog){var rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();}});dialog.addEventListener('close',function(){dialog.remove();},{once:true});dialog.showModal();
        }
        async function save(keys,draft){
            var files={},changes=keys.map(function(key,index){var rec=records[key];if(rec.file)files['image_'+index]=rec.file;if(rec.thumbnail)files['thumbnail_'+index]=rec.thumbnail;var photos=rec.photos.filter(function(photo){return photo.photo_id||!photo.remove;}).map(function(photo,photoIndex){if(photo.file&&!photo.remove)files['photos_'+index+'_'+photoIndex]=photo.file;return {photo_id:photo.photo_id||0,caption:photo.caption||'',remove:photo.remove};});return Object.assign({},rec.base,rec.changes,{key:key,photos:photos,achievement_id:rec.base.achievement_id||0,remove:rec.removed,status:draft?'draft':value(rec,'status')||'published'});});
            var result=await send('/admin/action/student-achievements-handler.php',{changes:JSON.stringify(changes)},files);
            if(!result.ok)throw Error(result.error||'Could not save achievements.');
            (result.records||[]).forEach(function(item){var rec=records[item.key];if(!rec)return;rec.base=item.record;rec.changes={};rec.file=null;rec.thumbnail=null;rec.photos=photoEntries(rec.base);if(rec.base.image_path)previewImage(rec,rec.base.image_path,'main');if(rec.base.thumbnail_path)previewImage(rec,rec.base.thumbnail_path,'thumbnail');nodes(rec).forEach(function(card){var badge=$('.achievement-editor-status',card);if(badge)badge.textContent=(rec.base.status==='published'?'Published':'Draft')+(Number(rec.base.is_featured)?' · Slideshow':'');});});
            keys.forEach(function(key){if(records[key].removed)delete records[key];});
            return result;
        }
        function failure(error){toast(error.message||'Could not save achievements.','error');}
        function remove(rec){confirmPop('Delete achievement?','This will be removed when you publish your changes.','Remove',function(){nodes(rec).forEach(function(node){node.remove();});if(rec.base.achievement_id)rec.removed=true;else delete records[keyOf(rec)];select(null);chrome();});}
        function draft(rec){closePop();savePageDraft();}
        function settings(rec){
            if(!rec)return;
            var status=h('select',{class:'form-select form-select-sm','aria-label':'Achievement visibility'},[h('option',{value:'draft',text:'Draft'}),h('option',{value:'published',text:'Published'})]);status.value=value(rec,'status')||'draft';
            var featured=h('input',{type:'checkbox','aria-label':'Show in the big slideshow'});featured.checked=!!Number(value(rec,'is_featured'));
            var description=h('textarea',{class:'form-control form-control-sm',rows:4,maxlength:12000,'aria-label':'Achievement description'});description.value=value(rec,'description')||'';
            var category=h('input',{class:'form-control form-control-sm',type:'text',maxlength:100,'aria-label':'New achievement category',placeholder:'Enter a new category',hidden:true,value:value(rec,'category_label')||''});
            var categoryNames=Array.from(new Set(Object.keys(records).filter(function(key){return !records[key].removed;}).map(function(key){return String(value(records[key],'category_label')||'').trim();}).filter(Boolean))).sort(function(a,b){return a.localeCompare(b);});
            var newCategoryOption=h('option',{value:'',text:'+ Add new category'});
            var categorySelect=h('select',{class:'form-select form-select-sm','aria-label':'Achievement category'},[h('option',{value:'',text:'Select category (optional)'})].concat(categoryNames.map(function(name){return h('option',{value:name,text:name});}),[newCategoryOption]));
            categorySelect.value=category.value;
            categorySelect.addEventListener('change',function(){var adding=categorySelect.selectedOptions[0]===newCategoryOption;category.hidden=!adding;category.value=adding?'':categorySelect.value;if(adding)category.focus();});
            var gallery=h('div',{class:'achievement-media-editor'});
            var uploadStatus=h('small',{class:'achievement-upload-status','data-achievement-upload-status':true,role:'status','aria-live':'polite'});
            function renderPhotos(){var active=rec.photos.filter(function(photo){return !photo.remove;}),queued=active.filter(function(photo){return photo.file;}).length;uploadStatus.textContent=active.length+' photos'+(queued?' · '+queued+' new photos ready to save':'');gallery.replaceChildren();active.forEach(function(photo){var caption=h('input',{class:'form-control form-control-sm',maxlength:255,'aria-label':'Achiever name / Photo caption',value:photo.caption||'',placeholder:'Achiever name / Caption (optional)',on:{input:function(){photo.caption=caption.value;chrome();}}});gallery.appendChild(h('div',{class:'achievement-media-row'},[h('img',{src:photo.file?previewUrl(photo.file):photo.image_path,alt:photo.caption||'Main photo'}),h('div',{},[photo.file?h('small',{class:'achievement-upload-filename',text:photo.file.name}):null,caption,h('button',{type:'button',class:'btn btn-sm btn-outline-danger',text:'Remove photo',on:{click:function(){photo.remove=true;renderPhotos();chrome();}}})])]));});}
            function queuePhotos(files){files=Array.from(files||[]);if(!files.length)return;if(rec.photos.filter(function(photo){return !photo.remove;}).length+files.length>20){toast('Choose up to 20 photos per achievement.','error');return;}if(files.some(function(file){return file.size>5*MB;})){toast('Each picture must be at most 5 MB.','error');return;}if(files.some(function(file){return file.type?!/^image\/(jpeg|png|webp|gif)$/.test(file.type):!(/\.(jpe?g|png|webp|gif)$/i.test(file.name));})){toast('Choose JPG, PNG, WebP or GIF photos.','error');return;}files.forEach(function(file){rec.photos.push({photo_id:0,caption:'',file:file,remove:false});});renderPhotos();chrome();}
            function addPhotos(){var input=h('input',{type:'file',multiple:true,accept:'image/jpeg,image/png,image/webp,image/gif','data-achievement-gallery-picker':true,hidden:true});input.addEventListener('change',function(){queuePhotos(input.files);input.remove();});input.addEventListener('cancel',function(){input.remove();},{once:true});D.body.appendChild(input);input.click();}
            var dropzone=h('button',{type:'button',class:'achievement-upload-dropzone','data-achievement-upload-dropzone':true,text:'Add achiever photos',on:{click:addPhotos,dragover:function(event){event.preventDefault();dropzone.classList.add('is-dragging');},dragleave:function(){dropzone.classList.remove('is-dragging');},drop:function(event){event.preventDefault();event.stopPropagation();dropzone.classList.remove('is-dragging');queuePhotos(event.dataTransfer.files);}}});
            function photoChoice(kind,title,hint){var source=kind==='thumbnail'?(rec.thumbnail||rec.base.thumbnail_path||rec.file||rec.base.image_path):(rec.file||rec.base.image_path);var image=h('img',{class:'achievement-choice-preview',alt:title,hidden:!source,src:source?(typeof source==='string'?source:previewUrl(source)):null}),empty=h('span',{class:'achievement-choice-empty',text:'No photo chosen',hidden:!!source});return h('section',{class:'achievement-photo-choice'},[h('strong',{text:title}),image,empty,h('p',{class:'ve-pop-hint',text:hint}),h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:kind==='thumbnail'?'Change card cover':'Change featured photo',on:{click:function(){pickImage(rec,kind,function(file){image.src=previewUrl(file);image.hidden=false;empty.hidden=true;});}}})]);}
            renderPhotos();
            popBelowToolbar(h('div',{class:'ve-pop-form'},[h('strong',{class:'ve-pop-label',text:'Achievement settings'}),h('label',{text:'Category'}),categorySelect,category,h('small',{class:'ve-pop-hint',text:'Choose an existing category or add a new one. Published categories appear in the filter beside Academic Year.'}),h('label',{text:'Description'}),description,h('div',{class:'achievement-photo-choices'},[
                photoChoice('thumbnail','Card cover photo','Shown on the small card under More Achievements.'),photoChoice('main','Featured slideshow photo (optional)','Shown in the large slideshow at the top. If left empty, the card cover is used.')
            ]),h('strong',{text:'Achiever photos'}),h('small',{class:'ve-pop-hint',text:'Photos visitors see after clicking an achievement. Photos appear in upload order, with the first uploaded photo shown first.'}),dropzone,h('small',{class:'ve-pop-hint',text:'Choose several photos together, or drag and drop them here. Up to 20 photos, 5 MB each. You can leave captions blank.'}),uploadStatus,gallery,h('label',{text:'Visibility'}),status,h('label',{},[featured,h('span',{text:' Include in the large slideshow at the top'})]),h('div',{class:'ve-pop-row'},[
                h('button',{type:'button',class:'btn btn-sm btn-gnc',text:'Apply',on:{click:function(){update(rec,'category_label',category.value.trim());update(rec,'description',description.value);update(rec,'status',status.value);update(rec,'is_featured',featured.checked?1:0);closePop();chrome();}}}),
                h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Save draft',on:{click:function(){update(rec,'category_label',category.value.trim());update(rec,'description',description.value);update(rec,'is_featured',featured.checked?1:0);draft(rec);}}})
            ])]),null,true);
        }
        function add(savedKey,savedRecord){
            if(!data||(!savedRecord&&S.busy))return;
            var grid=$('#achievementGrid',S.doc);if(!grid)return;
            var key=savedKey||('new-'+(++serial)),rec=savedRecord||entry({achievement_id:0,title:'',description:'',program_organization:'',category_label:'',academic_year:'',image_path:null,status:'published',is_featured:0,sort_order:0});records[key]=rec;
            function field(tag,key,label,max,cls){return h(tag,{'class':cls||'','data-ve-field':'achievement-field','data-ve-key':key,'data-ve-label':label,'data-ve-ph':'Add '+label.toLowerCase(),'data-ve-max':max||255,...(['description','program_organization','category_label'].includes(key)?{'data-warning-optional':'true'}:{}),...(key==='description'?{'data-ve-multiline':'1'}:{})});}
            var card=h('article',{class:'achievement-card','data-ve-achievement-id':key},[
                h('div',{class:'achievement-card-cover','data-ve-achievement-image':'thumbnail'},[h('div',{class:'achievement-card-placeholder achievement-cover-prompt'},[icon('image'),h('span',{text:'Add cover photo'})]),field('span','category_label','Category',100,'achievement-card-category')]),
                field('h3','title','Achievement title'),h('p',{class:'achievement-card-meta'},[field('span','program_organization','Program / Organization'),h('span',{text:' | Academic Year '}),field('span','academic_year','Academic year',9)])
            ]);
            var slot=$('[data-ve-achievement-add]',grid);if(slot)slot.replaceWith(card);else grid.appendChild(card);var empty=$('.achievement-grid-empty',S.doc);if(empty)empty.remove();bind(card);if(savedRecord)return;card.scrollIntoView({block:'center',behavior:'smooth'});var title=$('[data-ve-key="title"]',card);select({kind:'field',el:title});title.focus();chrome();
        }
        return {
            draftState:function(value){
                if(!value)return {records:records,serial:serial};records=value.records;serial=value.serial;
                Object.keys(records).forEach(function(key){var rec=records[key],node=S.doc.querySelector('[data-ve-achievement-id="'+cssEsc(key)+'"]');if(rec.removed){if(node)node.remove();}else if(!node)add(key,rec);});bind(S.doc);
            },
            draftRebind:function(){bind(S.doc);},
            mount:function(doc){try{data=JSON.parse(doc.getElementById('veAchievementData').textContent);}catch(error){data=null;}records={};if(!data)return false;(data.records||[]).forEach(function(row){records[row.achievement_id]=entry(row);});bind(doc);
                var filter=$('.achievement-filter',doc);if(filter)filter.addEventListener('submit',async function(event){event.preventDefault();if(!await guardDirty())return;var url=new URL(data.url,window.location.origin);new FormData(filter).forEach(function(value,key){if(value&&(key==='academic_year'||key==='category'))url.searchParams.set(key,value);});url.searchParams.set('ce_preview','1');loadFrame(url.pathname+url.search+'#more-achievements');});return false;},
            pick:function(target){if(target.closest('[data-ve-achievement-add]')){add();return {kind:'none'};}var button=target.closest('[data-ve-achievement-settings]');if(button){select({kind:'achievement-card',el:button.closest('[data-ve-achievement-id]')});settings(record(button));return {kind:'none'};}var field=fieldOf(target);if(field&&field.__ve)return {kind:'field',el:field};var image=target.closest('[data-ve-achievement-image]');if(image)return {kind:'achievement-image',el:image};var card=target.closest('[data-ve-achievement-id]');return card?{kind:'achievement-card',el:card}:null;},
            toolbar:function(selected){var rec=record(selected.el);if(!rec)return [];var items=[{type:'chip',text:value(rec,'status')==='published'?'Published':'Draft',icon:'circle-fill'}];if(selected.kind==='achievement-image'){var kind=selected.el.getAttribute('data-ve-achievement-image')==='thumbnail'?'thumbnail':'main';items.push({icon:'image',label:kind==='thumbnail'?'Change card cover':'Change featured photo',primary:true,run:function(){pickImage(rec,kind);}});}items.push({icon:'sliders',label:'Achievement settings',run:function(){settings(rec);}},{icon:'people',label:'View achievers',run:function(){showAchievers(rec);}});if(value(rec,'status')!=='published')items.push({icon:'cloud-upload',label:'Publish achievement',run:function(){update(rec,'status','published');chrome();renderToolbar();}});items.push({icon:'save',label:'Save draft',run:function(){draft(rec);}},{icon:'trash',label:'Delete achievement',danger:true,run:function(){remove(rec);}});return items;},
            onLink:function(link){var id=link.getAttribute('data-achievement-open');if(id&&records[id]){showAchievers(records[id]);return true;}var url=new URL(link.getAttribute('href'),window.location.origin);if(url.origin===window.location.origin&&/\/student-achievement\.php$/.test(url.pathname)){(async function(){if(await guardDirty())loadFrame(withParam(url.pathname+url.search+url.hash,'ce_preview','1'));})();return true;}return false;},
            dirtyCount:dirty,
            status:function(){return {text:dirty()?plural(dirty(),'unsaved achievement'):'Everything here is saved',tone:dirty()?'warn':'ok'};},
            actions:function(){return {secondary:{label:'Add Achievement',icon:'plus-circle',disabled:!data},primary:{label:'Publish',icon:'cloud-upload',disabled:!data||!dirty()}};},
            runSecondary:add,
            runPrimary:function(){if(!dirty())return;confirmPublish('Publish these achievement changes?',function(){run('Publishing…',async function(){try{await save(Object.keys(records).filter(function(key){return changed(records[key]);}));reloadEditor('Published. Your changes are live now.',{page:'student-achievements'},true);}catch(error){failure(error);}});});}
        };
    }

    function OrganizationAdapter() {
        var data = null, records = {}, settings = {}, pageFiles = {}, pageRemoved = {}, serial = 0;
        var endpoint = '/admin/action/student-organizations-handler.php';
        function entry(row) { return {base:row, changes:{}, files:{}, removed:{}, gallery:[], galleryRemoved:[]}; }
        function changed(rec) { return !rec.base.org_id || Object.keys(rec.changes).length || Object.keys(rec.files).length || Object.keys(rec.removed).length || rec.gallery.length || rec.galleryRemoved.length; }
        function dirty() { return Object.keys(settings).length + Object.keys(pageFiles).length + Object.keys(pageRemoved).length + Object.keys(records).filter(function(id){return changed(records[id]);}).length; }
        function record(el) { var card=el&&el.closest('[data-ve-org-card]'); return records[card?card.getAttribute('data-ve-org-card'):data.organization&&data.organization.org_id]; }
        function value(rec,key) { return Object.prototype.hasOwnProperty.call(rec.changes,key)?rec.changes[key]:rec.base[key]; }
        function editFields(root) {
            $$('[data-ve-field]',root).forEach(function(el){
                var type=el.getAttribute('data-ve-field'),key=el.getAttribute('data-ve-key');
                if(type!=='org-field'&&type!=='org-setting')return;
                if(!el.getAttribute('data-ve-ph'))el.setAttribute('data-ve-ph','Add '+(el.getAttribute('data-ve-label')||key).toLowerCase());
                makeField(el,function(_,text){var rec=record(el), edits=type==='org-setting'?settings:rec.changes;
                    var baseline=type==='org-setting'?data.fields[key].value:rec.base[key]||'';
                    if(text===baseline)delete edits[key];else edits[key]=text;
                });
            });
        }
        function request(fields,files) { return send(endpoint,fields,files).then(function(result){if(!result.ok)throw new Error(result.error);return result;}); }
        function failure(error) { toast(error.message||'Could not save this change.','error'); }
        async function save(rec,status) {
            var payload=Object.assign({},rec.changes,{action:'save',org_id:rec.base.org_id||0,status:status||value(rec,'status')||'draft'});
            if(!rec.base.org_id)payload.name=value(rec,'name')||'';
            Object.keys(rec.removed).forEach(function(key){payload['remove_'+key]='1';});
            rec.galleryRemoved.forEach(function(id,index){payload['remove_gallery['+index+']']=id;});
            var uploads=Object.assign({},rec.files);rec.gallery.forEach(function(file,index){if(file)uploads['gallery['+index+']']=file;});
            var result=await request(payload,uploads);rec.base=result.organization;rec.changes={};rec.files={};rec.removed={};rec.gallery=[];rec.galleryRemoved=[];
            var id=Object.keys(records).find(function(key){return records[key]===rec;});var card=$('[data-ve-org-card="'+id+'"]',S.doc);
            if(card){var badge=$('.student-org-draft',card);if(rec.base.status==='draft'&&!badge){badge=S.doc.createElement('span');badge.className='student-org-draft';$('.student-org-card-cover',card).appendChild(badge);}if(badge){badge.textContent='Unpublished';badge.hidden=rec.base.status==='published';}}
            if(data.organization){data.organization=rec.base;
                var gallery=$('.org-gallery',S.doc),add=gallery&&$('[data-ve-org-gallery-add]',gallery);
                if(gallery&&Array.isArray(result.gallery)){
                    $$('[data-ve-org-gallery]',gallery).forEach(function(tile){tile.remove();});
                    result.gallery.forEach(function(image){gallery.insertBefore(h('a',{'data-ve-org-gallery':image.image_id,href:image.image_url,target:'_blank',rel:'noopener','aria-label':'View gallery photo'},[h('img',{src:image.image_url,alt:rec.base.name+' gallery photo'})]),add);});
                }
            }
            return result.organization;
        }
        function addOrganization(savedKey,savedRecord) {
            if(!data||(!savedRecord&&S.busy))return;
            var grid=$('.student-org-grid',S.doc);
            if(!grid){navigate(data.listingUrl+'?ce_preview=1&add_org=1');return;}
            var id=savedKey||('new-'+(++serial)), rec=savedRecord||entry({name:'',description:'',about_text:'',vision:'',mission:'',facebook_url:'',status:'draft',category_id:null});records[id]=rec;
            var card=h('article',{'class':'student-org-card','data-ve-org-card':id,'data-ve-label':'New organization'},[
                h('div',{class:'student-org-card-cover'},[h('span',{class:'org-cover-picture','data-ve-org-media':'cover','data-warning-image':true,'data-warning-label':'Organization cover',text:'Add cover image'}),h('span',{class:'student-org-draft',text:'Unpublished'})]),
                h('div',{class:'student-org-card-body'},[
                    h('div',{class:'student-org-card-heading'},[h('span',{class:'student-org-card-logo','data-ve-org-media':'logo','data-warning-image':true,'data-warning-label':'Organization logo'},[icon('image')]),h('h3',{'data-ve-field':'org-field','data-ve-key':'name','data-ve-label':'Organization name','data-ve-max':255})]),
                    h('div',{class:'cms-rich','data-ve-rich':true,'data-ve-field':'org-field','data-ve-key':'description','data-ve-label':'Short description','data-ve-max':1500,'data-ve-multiline':true}),
                    h('button',{type:'button',class:'org-canvas-control','data-ve-org-details':true,text:'Edit organization page'}),
                    h('button',{type:'button',class:'org-canvas-control','data-ve-org-assign-category':true,text:'Assign category'})])]);
            grid.insertBefore(card,$('[data-ve-org-add]',grid));var empty=$('.student-org-empty',S.doc);if(empty)empty.remove();editFields(card);
            if(savedRecord)return;card.scrollIntoView({block:'center',behavior:'smooth'});var field=$('[data-ve-key="name"]',card);select({kind:'field',el:field});field.focus();chrome();
        }
        function openDetails(rec) {
            if(S.busy)return;
            // Save only this unpublished card before opening its full page; other edits remain guarded.
            var others=Object.keys(records).some(function(id){return records[id]!==rec&&changed(records[id]);})||Object.keys(settings).length||Object.keys(pageFiles).length||Object.keys(pageRemoved).length;
            if(others){toast('Publish or save your other changes before opening another page.','warn');return;}
            if(!value(rec,'name')||!value(rec,'name').trim()){toast('Enter the organization name on its card first.','warn');return;}
            if(changed(rec)){
                if(rec.base.status==='published'){toast('Publish your card changes before opening its full page.','warn');return;}
                run('Saving draft...',async function(){try{var row=await save(rec,'draft');navigate(data.listingUrl.replace('student-org.php','dynamic-org.php')+'?slug='+encodeURIComponent(row.slug));}catch(error){failure(error);}});
            }else navigate(data.listingUrl.replace('student-org.php','dynamic-org.php')+'?slug='+encodeURIComponent(rec.base.slug));
        }
        function categories(rec) {
            var selectBox=h('select',{class:'form-select form-select-sm','aria-label':rec?'Organization category':'Category'});
            selectBox.appendChild(h('option',{value:'',text:rec?'Uncategorized':'Choose category'}));
            data.categories.forEach(function(cat){selectBox.appendChild(h('option',{value:cat.category_id,text:cat.name}));});
            if(rec)selectBox.value=value(rec,'category_id')||'';
            function selected(){return data.categories.find(function(cat){return String(cat.category_id)===selectBox.value;});}
            function categoryName(cat){askText({label:cat?'Rename category':'New category',value:cat?cat.name:'',max:100,required:true,done:function(name){run('Saving category...',async function(){try{var result=await request({action:'save_category',category_id:cat?cat.category_id:0,name:name});refreshCategories(result.categories);categories(rec);}catch(error){failure(error);}});}});}
            var buttons=[h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Add category',on:{click:function(){categoryName(null);}}}),h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Rename',on:{click:function(){var cat=selected();if(cat)categoryName(cat);}}}),h('button',{type:'button',class:'btn btn-sm btn-outline-danger',text:'Delete',on:{click:function(){var cat=selected();if(!cat)return;confirmPop('Delete category?','Organizations will be kept without this category.','Delete category',function(){run('Deleting category...',async function(){try{var result=await request({action:'delete_category',category_id:cat.category_id});refreshCategories(result.categories);categories(rec);}catch(error){failure(error);}});});}}})];
            if(rec)buttons.unshift(h('button',{type:'button',class:'btn btn-sm btn-gnc',text:'Apply',on:{click:function(){rec.changes.category_id=selectBox.value;var card=$('[data-ve-org-card="'+Object.keys(records).find(function(id){return records[id]===rec;})+'"]',S.doc);if(card){var badge=$('.student-org-category',card);if(!badge){badge=S.doc.createElement('span');badge.className='student-org-category';$('.student-org-card-cover',card).appendChild(badge);}badge.textContent=selected()?selected().name:'Uncategorized';}assignCategoryLabel(rec,selected());closePop();chrome();}}}));
            popBelowToolbar(h('div',{class:'ve-pop-form'},[h('strong',{class:'ve-pop-label',text:rec?'Organization category':'Manage categories'}),selectBox,h('div',{class:'ve-pop-row'},buttons)]),null,true);
        }
        function assignCategoryLabel(rec,category) {
            $$('[data-ve-org-assign-category]',S.doc).forEach(function(button){if(record(button)===rec)button.textContent='Assign category: '+(category?category.name:'Uncategorized');});
        }
        function refreshCategories(categories) {
            data.categories=categories;
            function category(id){return categories.find(function(cat){return String(cat.category_id)===String(id);});}
            Object.keys(records).forEach(function(id){var rec=records[id];if(rec.base.category_id&&!category(rec.base.category_id))rec.base.category_id=null;
                if(rec.changes.category_id&&!category(rec.changes.category_id))rec.changes.category_id='';
                var card=$('[data-ve-org-card="'+id+'"]',S.doc),badge=card&&$('.student-org-category',card),cat=category(value(rec,'category_id'));if(badge)badge.textContent=cat?cat.name:'Uncategorized';assignCategoryLabel(rec,cat);});
            var nav=$('.student-org-filters',S.doc);if(!nav)return;
            var active=nav.querySelector('a.is-active'),selected=active?new URL(active.href).searchParams.get('category'):null;
            $$('a',nav).slice(1).forEach(function(link){link.remove();});
            categories.forEach(function(cat){var link=h('a',{href:data.listingUrl+'?ce_preview=1&category='+cat.category_id,text:cat.name});if(String(cat.category_id)===selected){link.className='is-active';link.setAttribute('aria-current','true');}nav.insertBefore(link,$('[data-ve-org-categories]',nav));});
            if(!selected||!category(selected)){var all=nav.querySelector('a');all.className='is-active';all.setAttribute('aria-current','true');}
        }
        function galleryAdd() {
            var rec=record(null);if(!rec)return;
            var visible=$$('[data-ve-org-gallery]',S.doc).length;
            if(visible>=10){toast('Use up to 10 gallery images.','warn');return;}
            pickFile('image/jpeg,image/png,image/webp,image/gif',function(file){
                if(file.size>5*MB){toast('Use an image of up to 5 MB.','error');return;}
                var id='new-'+rec.gallery.length;rec.gallery.push(file);
                var tile=h('a',{'data-ve-org-gallery':id,href:'#'},[h('img',{src:previewUrl(file),alt:'New gallery image'})]);
                var add=$('[data-ve-org-gallery-add]',S.doc);add.parentNode.insertBefore(tile,add);chrome();
            });
        }
        function saveUnpublished(rec) {
            closePop();savePageDraft();
        }
        function move(rec,direction) {
            if(dirty()){toast('Save or publish your changes before changing the order.','warn');return;}
            run('Updating order...',async function(){try{await request({action:'move',org_id:rec.base.org_id,direction:direction});loadFrame(S.url,true);}catch(error){failure(error);}});
        }
        function removeOrganization(rec,el) {
            if(data.organization&&Object.keys(settings).length){toast('Publish your page heading changes before deleting this organization.','warn');return;}
            confirmPop('Delete organization?','This removes the organization and its gallery. This cannot be undone.','Delete organization',function(){
                run('Deleting organization...',async function(){try{
                    if(rec.base.org_id)await request({action:'delete',org_id:rec.base.org_id});
                    Object.keys(records).forEach(function(id){if(records[id]===rec)delete records[id];});
                    var card=el&&el.closest('[data-ve-org-card]');if(card)card.remove();select(null);closePop();
                    if(data.organization)loadFrame(data.listingUrl+'?ce_preview=1');else chrome();
                }catch(error){failure(error);}});
            });
        }
        async function navigate(url) {
            if (!await guardDirty()) return;
            loadFrame(withParam(url, 'ce_preview', '1'));
            var sub=$('#veSub'); if (sub) sub.value=withParam(url,'ce_preview','1');
        }
        return {
            draftState:function(value){
                if(!value)return {records:records,settings:settings,pageFiles:pageFiles,pageRemoved:pageRemoved,serial:serial};records=value.records;settings=value.settings;pageFiles=value.pageFiles;pageRemoved=value.pageRemoved;serial=value.serial;
                if(!data.organization)Object.keys(records).forEach(function(key){if(!S.doc.querySelector('[data-ve-org-card="'+cssEsc(key)+'"]'))addOrganization(key,records[key]);});editFields(S.doc);
                if(data.organization){
                    var rec=record(null),add=$('[data-ve-org-gallery-add]',S.doc);
                    if(rec&&add){
                        rec.galleryRemoved.forEach(function(id){var tile=S.doc.querySelector('[data-ve-org-gallery="'+cssEsc(String(id))+'"]');if(tile)tile.remove();});
                        rec.gallery.forEach(function(file,index){if(file)add.before(h('a',{'data-ve-org-gallery':'new-'+index,href:'#'},[h('img',{src:previewUrl(file),alt:'New gallery image'})]));});
                    }
                }
            },
            draftRebind:function(){editFields(S.doc);},
            mount: function (doc) {
                try { data=JSON.parse(doc.getElementById('veOrgData').textContent); } catch (_) { data=null; }
                records={};settings={};pageFiles={};pageRemoved={};if(!data)return false;
                (data.organization?[data.organization]:data.organizations||[]).forEach(function(row){records[row.org_id]=entry(row);});
                editFields(doc);
                if(new URL(S.frame.src).searchParams.get('add_org')==='1')addOrganization();
                return false;
            },
            pick: function (target) {
                var orgIcon=target.closest('[data-ve-org-icon]');if(orgIcon)return {kind:'org-icon',el:orgIcon,key:orgIcon.getAttribute('data-ve-org-icon')};
                var assign=target.closest('[data-ve-org-assign-category]');
                if(assign){select({kind:'org-assign-category',el:assign});categories(record(assign));return {kind:'none'};}
                if(target.closest('[data-ve-org-add]')){addOrganization();return {kind:'none'};}
                if(target.closest('[data-ve-org-details]')){openDetails(record(target));return {kind:'none'};}
                if(target.closest('[data-ve-org-categories]'))return {kind:'org-categories',el:target.closest('[data-ve-org-categories]')};
                if(target.closest('[data-ve-org-gallery-add]')){galleryAdd();return {kind:'none'};}
                var gallery=target.closest('[data-ve-org-gallery]');if(gallery)return {kind:'org-gallery',el:gallery};
                var field=fieldOf(target); if (field && field.__ve) return {kind:'field',el:field};
                var pageMedia=target.closest('[data-ve-org-page-media]');if(pageMedia)return {kind:'org-page-media',el:pageMedia,key:pageMedia.getAttribute('data-ve-org-page-media')};
                var media=target.closest('[data-ve-org-media]'); if (media) return {kind:'org-media',el:media,key:media.getAttribute('data-ve-org-media')};
                var card=target.closest('[data-ve-org-card]'); if (card) return {kind:'org-card',el:card};
                return null;
            },
            toolbar: function (selected) {
                if(selected.kind==='org-icon')return [{icon:'grid-3x3-gap',label:'Change icon',primary:true,run:function(){
                    libraryIconPicker(data.icons,settings[selected.key]||data.fields[selected.key].value,function(spec,choice){
                        if(spec===data.fields[selected.key].value)delete settings[selected.key];else settings[selected.key]=spec;
                        var node=S.doc.createElement(choice.path?'img':choice.material?'span':'i');node.setAttribute('aria-hidden','true');
                        if(choice.path){node.src=choice.path;node.alt='';}else if(choice.material){node.className='material-symbols-outlined';node.textContent=choice.material;}else node.className='bi bi-'+choice.glyph;
                        selected.el.replaceChildren(node);
                    });
                }}];
                if(selected.kind==='org-page-media')return [
                    {type:'chip',icon:'image',text:data.images[selected.key].label},
                    {icon:'image',label:'Replace picture',primary:true,run:function(){pickFile('image/jpeg,image/png,image/webp,image/gif',function(file){
                        if(file.size>5*MB){toast('Use an image of up to 5 MB.','error');return;}
                        pageFiles[selected.key]=file;delete pageRemoved[selected.key];selected.el.querySelector('img').src=previewUrl(file);chrome();
                    });}},
                    {icon:'arrow-counterclockwise',label:'Use default picture',run:function(){pageRemoved[selected.key]=true;delete pageFiles[selected.key];selected.el.querySelector('img').src=data.images[selected.key].fallback;chrome();}}
                ];
                if(selected.kind==='org-assign-category')return [{icon:'tags',label:'Category',run:function(){categories(record(selected.el));}}];
                if(selected.kind==='org-categories')return [{icon:'tags',label:'Manage categories',primary:true,run:function(){categories(null);}}];
                if(selected.kind==='org-gallery')return [{icon:'trash',label:'Remove gallery image',danger:true,run:function(){var rec=record(null),id=selected.el.getAttribute('data-ve-org-gallery');if(id.indexOf('new-')===0)rec.gallery[Number(id.slice(4))]=null;else rec.galleryRemoved.push(id);selected.el.remove();select(null);chrome();}}];
                var rec=record(selected.el);
                if(selected.kind==='field'||selected.kind==='org-card'){
                    var items=selected.kind==='field'?[{type:'chip',icon:'pencil',text:selected.el.__ve.label||'Text'}]:[];
                    if(rec)items=items.concat([{icon:'pencil-square',label:'Edit page',run:function(){openDetails(rec);}},{icon:'tags',label:'Category',run:function(){categories(rec);}},{icon:'save',label:'Save draft',run:function(){saveUnpublished(rec);}},{icon:'cloud-upload',title:'Publish organization',run:function(){confirmPublish('Publish this organization?',function(){run('Publishing...',async function(){try{await save(rec,'published');toast('Published. Your changes are live now.','ok');chrome();renderToolbar();}catch(error){failure(error);}});});}},{icon:'arrow-left',title:'Move earlier',run:function(){move(rec,-1);}},{icon:'arrow-right',title:'Move later',run:function(){move(rec,1);}},{icon:'trash',title:'Delete organization',danger:true,run:function(){removeOrganization(rec,selected.el);}}]);
                    return items;
                }
                if (selected.kind!=='org-media') return [];
                return [{icon:'image',label:'Replace picture',primary:true,run:function(){
                    pickFile('image/jpeg,image/png,image/webp,image/gif',function(file){
                        if (file.size>5*MB) {toast('Use an image of up to 5 MB.','error');return;}
                        rec.files[selected.key]=file; delete rec.removed[selected.key];
                        var image=selected.el.tagName==='IMG'?selected.el:selected.el.querySelector('img');
                        if (!image) {image=S.doc.createElement('img');selected.el.replaceChildren(image);selected.el.classList.add('has-image');}
                        selected.el.style.opacity='';image.src=previewUrl(file);chrome();renderToolbar();
                    });
                }},{icon:'trash',title:'Remove picture',danger:true,run:function(){rec.removed[selected.key]=true;delete rec.files[selected.key];selected.el.replaceChildren();selected.el.style.opacity='.25';chrome();}}];
            },
            onLink: function (link) {
                var url=new URL(link.getAttribute('href'),window.location.origin);
                if (url.origin===window.location.origin && /\/(student-org|dynamic-org)\.php$/.test(url.pathname)) {navigate(url.pathname+url.search);return true;}
                return false;
            },
            dirtyCount: dirty,
            status: function(){return {text:dirty()?plural(dirty(),'unsaved change'):'Everything here is saved',tone:dirty()?'warn':'ok'};},
            hasPageOptions: function(){return !!data;},
            pageOptions: function(x,y){var rec=record(null);var buttons=[h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Manage categories',on:{click:function(){categories(null);}}})];if(rec)buttons.push(h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Save draft',on:{click:function(){closePop();saveUnpublished(rec);}}}),h('button',{type:'button',class:'btn btn-sm btn-outline-danger',text:'Delete organization',on:{click:function(){removeOrganization(rec,null);}}}));openPop(h('div',{class:'ve-pop-form'},buttons),x,y);},
            actions: function(){return {secondary:{label:'Add Organization',icon:'plus-circle'},primary:{label:'Publish',icon:'cloud-upload',disabled:!data||(!dirty()&&(!data.organization||data.organization.status==='published'))}};},
            runSecondary: addOrganization,
            runPrimary: function(){
                if (!data) return;
                confirmPublish('Publish these organization changes?',function(){
                    run('Publishing…',function(){
                        return (async function(){try{
                            if(Object.keys(settings).length||Object.keys(pageFiles).length||Object.keys(pageRemoved).length){var page=await request({action:'save_page',texts:JSON.stringify(settings),remove_images:JSON.stringify(Object.keys(pageRemoved))},pageFiles);Object.keys(settings).forEach(function(key){data.fields[key].value=settings[key];});if(page.images)data.images=page.images;settings={};pageFiles={};pageRemoved={};}
                            for(var id of Object.keys(records)){var rec=records[id];if(changed(rec)||(data.organization&&rec.base.status!=='published'))await save(rec,'published');}
                            reloadEditor('Published. Your changes are live now.',entityRoute(S.url),true);
                        }catch(error){failure(error);}})();
                    });
                });
            }
        };
    }
    function SportsAdapter() {
        var data=null, rows=[], texts={}, files={}, backgroundFiles={}, teams=[],teamFiles={},facilities=[],facilityFiles={},baseline='',preparing=0,speed=1,motion=true,facebookUrl='';
        function payload(){return {texts:texts,speed:speed,facebook_url:facebookUrl,sports:rows.map(function(row){return {id:row.id,name:row.name,type:row.type};}),teams:teams.map(function(team){return {id:team.id,name:team.name};}),facilities:facilities.map(function(facility){return {id:facility.id,name:facility.name};})};}
        function editFacebook(){
            askText({label:'Facebook page address',anchor:S.doc.querySelector('[data-ve-sports-facebook]'),value:facebookUrl,max:255,placeholder:'https://www.facebook.com/your-page',hint:'Leave blank to remove the Facebook page. Publish to save.',done:function(value){
                value=value.trim();
                if(value&&!/^https?:\/\/(www\.)?facebook\.com\/[^\s]*$/i.test(value)){toast('Use a Facebook page address starting with https://www.facebook.com/','error');return;}
                facebookUrl=value;
                var box=S.doc.querySelector('[data-ve-sports-facebook]');
                if(box){
                    box.setAttribute('data-ve-link',value);
                    var old=box.querySelector('iframe');if(old)old.remove();
                    var button=box.querySelector('[data-ve-sports-facebook-edit]');
                    if(value){var frame=h('iframe',{src:'https://www.facebook.com/plugins/page.php?href='+encodeURIComponent(value)+'&tabs=&width=500&height=130&small_header=false&adapt_container_width=true&hide_cover=true&show_facepile=false&show_posts=false',width:'500',height:'130',title:'GNC Sports Club Facebook page',loading:'lazy'});box.insertBefore(frame,button);}
                    if(button)button.textContent=value?'Change Facebook page link':'Add the Facebook page link';
                }
                renderToolbar();chrome();
            }});
        }
        function setMotion(on){
            motion=on;var page=S.doc.querySelector('[data-ve-sports-preview]'),button=S.doc.querySelector('[data-ve-sports-motion]');
            if(page)page.classList.toggle('sports-motion-preview',motion);
            if(button){button.textContent=motion?'Edit cards':'Play slideshow';button.setAttribute('aria-pressed',String(motion));}
            if(motion){var slideshow=S.doc.querySelector('.athletics-slideshow'),pause=S.doc.querySelector('[data-sports-pause]');if(slideshow)slideshow.classList.remove('is-paused');if(pause){pause.setAttribute('aria-pressed','false');pause.textContent='Pause slideshow';}}
        }
        function paintSpeed(){
            var range=S.doc.querySelector('[data-ve-sports-speed]'),output=S.doc.querySelector('[data-ve-sports-speed-value]'),track=S.doc.querySelector('.athletics-sports-track');
            if(range){range.value=String(speed);range.setAttribute('aria-valuetext',speed+' times normal speed');}
            if(output)output.textContent=speed+'×';
            if(track)track.style.setProperty('--sports-scroll-duration',(38/speed)+'s');
            var slower=S.doc.querySelector('[data-ve-sports-slower]'),faster=S.doc.querySelector('[data-ve-sports-faster]');
            if(slower)slower.disabled=speed<=0.25;if(faster)faster.disabled=speed>=3;
        }
        function setSpeed(value){if(S.preview||S.busy)return;value=Number(value);if(!Number.isFinite(value))return;speed=Math.max(0.25,Math.min(3,Math.round(value*4)/4));paintSpeed();setMotion(true);chrome();}
        function snapshot(){return JSON.stringify(payload());}
        function dirty(){return (snapshot()!==baseline?1:0)+Object.keys(files).length+Object.keys(backgroundFiles).length+Object.keys(teamFiles).length+Object.keys(facilityFiles).length+preparing;}
        function chooseImage(done,target,preserveImage){
            pickFile('image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',function(file){
                if(file.size>5*MB){toast('Choose an image of up to 5 MB.','error');return;}
                preparing++;chrome();
                var api=window.GNCImageFraming;
                Promise.resolve().then(function(){return api&&api.cacheUpload?api.cacheUpload(file):file;}).then(done).catch(function(error){
                    toast(error.message||'Could not read the selected image. Choose it again.','error');
                }).finally(function(){preparing--;renderToolbar();chrome();});
            },false,target,preserveImage);
        }
        function marquee(){if(S.win.GNCSportsSlideshow)S.win.GNCSportsSlideshow.refresh(S.doc);}
        function rowOf(el){var card=el&&el.closest('[data-ve-sport]');return card&&rows.find(function(row){return row.id===card.getAttribute('data-ve-sport');});}
        function bindSport(card){
            $$('[data-ve-field]',card).forEach(function(el){
                makeField(el,function(node,value){var row=rowOf(node);if(row){row[node.getAttribute('data-ve-field')==='sport-name'?'name':'type']=value;marquee();}});
            });
        }
        function add(saved){
            if(!data||(!saved&&(S.preview||S.busy)))return;
            setMotion(false);
            if(!saved&&rows.length>=30){toast('Use up to 30 sports programs.','error');return;}
            var row=saved||{id:'sport-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,9),name:'New sport',type:'',image:''};
            if(!saved)rows.push(row);
            var name=h('strong',{'data-ve-field':'sport-name','data-ve-label':'Sport name','data-ve-max':'120',text:row.name});
            var label=h('span',{'data-ve-field':'sport-type','data-ve-label':'Sport label','data-ve-max':'80','data-ve-ph':'Add label (optional)','data-warning-optional':'true'});
            var card=h('article',{class:'athletics-sport athletics-sport--placeholder','data-ve-sport':row.id},[
                h('img',{class:'athletics-sport-image',hidden:true,alt:'','data-ve-sport-image':true}),h('h3',{class:'athletics-sport-title'},[label,name])]);
            S.doc.querySelector('[data-sports-original]').appendChild(card);bindSport(card);marquee();chrome();if(saved)return;
            card.scrollIntoView({block:'nearest',inline:'nearest',behavior:'smooth'});name.focus();
        }
        function replace(card){
            var row=rowOf(card);if(!row)return;
            setMotion(false);
            chooseImage(function(file){
                if(rows.indexOf(row)<0)return;
                files[row.id]=file;var image=card.querySelector('[data-ve-sport-image]');image.src=previewUrl(file);image.removeAttribute('hidden');card.classList.remove('athletics-sport--placeholder');marquee();renderToolbar();chrome();
            },card);
        }
        function replaceBackground(key){
            if(!data||S.preview||S.busy||['hero','showcase'].indexOf(key)<0)return;
            var area=S.doc.querySelector('[data-ve-sports-background-area="'+key+'"]');if(!area)return;
            chooseImage(function(file){
                backgroundFiles[key]=file;var url=previewUrl(file);
                if(key==='hero')area.src=url;else area.style.backgroundImage='url('+JSON.stringify(url)+')';
                renderToolbar();chrome();
            },area);
        }
        function move(card,delta){
            setMotion(false);
            var row=rowOf(card),i=rows.indexOf(row),other=rows[i+delta];if(!other)return;
            rows[i]=other;rows[i+delta]=row;
            var target=S.doc.querySelector('[data-ve-sport="'+other.id+'"]'),group=card.parentNode;
            if(delta<0)group.insertBefore(card,target);else group.insertBefore(target,card);
            marquee();renderToolbar();chrome();
        }
        function teamOf(el){var figure=el&&el.closest('[data-ve-team]');return figure&&teams.find(function(team){return team.id===figure.getAttribute('data-ve-team');});}
        function bindTeam(figure){
            var name=figure.querySelector('[data-ve-field="team-name"]');if(name)makeField(name,function(el,value){var team=teamOf(el);if(team){team.name=value;figure.querySelector('[data-ve-team-image]').setAttribute('alt',value);}});
        }
        function replaceTeam(figure){
            var team=teamOf(figure);if(!team)return;
            chooseImage(function(file){
                if(teams.indexOf(team)<0)return;
                teamFiles[team.id]=file;var image=figure.querySelector('[data-ve-team-image]');image.src=previewUrl(file);image.removeAttribute('hidden');
                var placeholder=figure.querySelector('[data-ve-team-upload]');if(placeholder)placeholder.setAttribute('hidden','');chrome();renderToolbar();
            },figure.querySelector('[data-ve-team-image]'),true);
        }
        function addTeam(saved){
            if(!data||(!saved&&(S.preview||S.busy)))return;if(!saved&&teams.length>=20){toast('Use up to 20 varsity team logos.','error');return;}
            var team=saved||{id:'team-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,9),name:'New team',image:''};if(!saved)teams.push(team);
            var figure=h('figure',{class:'athletics-team','data-ve-team':team.id},[
                h('img',{class:'athletics-team-logo',hidden:true,alt:team.name,'data-ve-team-image':true}),
                h('button',{type:'button',class:'athletics-team-upload','data-ve-team-upload':team.id,text:'Upload team logo'}),
                h('figcaption',{'data-ve-field':'team-name','data-ve-label':'Team name','data-ve-max':120,text:team.name})]);
            S.doc.querySelector('[data-ve-teams-grid]').appendChild(figure);bindTeam(figure);if(saved)return;figure.scrollIntoView({block:'nearest'});chrome();replaceTeam(figure);
        }
        function moveTeam(figure,delta){
            var team=teamOf(figure),i=teams.indexOf(team),other=teams[i+delta];if(!other)return;
            teams[i]=other;teams[i+delta]=team;var target=S.doc.querySelector('[data-ve-team="'+other.id+'"]');
            if(delta<0)figure.parentNode.insertBefore(figure,target);else figure.parentNode.insertBefore(target,figure);chrome();renderToolbar();
        }
        function facilityOf(el){var figure=el&&el.closest('[data-ve-facility]');return figure&&facilities.find(function(item){return item.id===figure.getAttribute('data-ve-facility');});}
        function refreshFacilities(index){if(S.win.GNCSportsSlideshow&&S.win.GNCSportsSlideshow.refreshFacilities)S.win.GNCSportsSlideshow.refreshFacilities(S.doc,index);}
        function bindFacility(figure){var name=figure.querySelector('[data-ve-field="facility-name"]');if(name)makeField(name,function(el,value){var facility=facilityOf(el);if(facility){facility.name=value;figure.querySelector('[data-ve-facility-image]').setAttribute('alt',value);}});}
        function replaceFacility(figure){
            var facility=facilityOf(figure);if(!facility)return;
            chooseImage(function(file){
                if(facilities.indexOf(facility)<0)return;facilityFiles[facility.id]=file;
                var image=figure.querySelector('[data-ve-facility-image]');image.src=previewUrl(file);image.removeAttribute('hidden');
                var placeholder=figure.querySelector('[data-ve-facility-upload]');if(placeholder)placeholder.setAttribute('hidden','');chrome();renderToolbar();
            },figure);
        }
        function addFacility(saved){
            if(!data||(!saved&&(S.preview||S.busy)))return;if(!saved&&facilities.length>=24){toast('Use up to 24 facility photos.','error');return;}
            var facility=saved||{id:'facility-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,9),name:'New facility',image:''};if(!saved)facilities.push(facility);
            var figure=h('figure',{class:'athletics-facility-slide fcar-slide','data-ve-facility':facility.id,role:'group','aria-roledescription':'slide'},[
                h('img',{class:'athletics-facility-photo',hidden:true,alt:facility.name,'data-ve-facility-image':true}),
                h('button',{type:'button',class:'athletics-facility-upload','data-ve-facility-upload':true,text:'Upload facility photo'}),
                h('figcaption',{class:'fcar-caption','data-ve-field':'facility-name','data-ve-label':'Facility name','data-ve-max':120,text:facility.name})]);
            S.doc.querySelector('.athletics-facilities-stage').appendChild(figure);bindFacility(figure);refreshFacilities(facilities.length-1);if(saved)return;figure.scrollIntoView({block:'nearest'});chrome();replaceFacility(figure);
        }
        function moveFacility(figure,delta){
            var facility=facilityOf(figure),index=facilities.indexOf(facility),other=facilities[index+delta];if(!other)return;
            facilities[index]=other;facilities[index+delta]=facility;var target=S.doc.querySelector('[data-ve-facility="'+other.id+'"]');
            if(delta<0)figure.parentNode.insertBefore(figure,target);else figure.parentNode.insertBefore(target,figure);refreshFacilities(index+delta);chrome();renderToolbar();
        }
        return {
            draftState:function(value){
                if(!value)return {rows:rows,texts:texts,files:files,backgroundFiles:backgroundFiles,teams:teams,teamFiles:teamFiles,facilities:facilities,facilityFiles:facilityFiles,baseline:baseline,speed:speed,facebookUrl:facebookUrl};
                rows=value.rows;texts=value.texts;files=value.files;backgroundFiles=value.backgroundFiles;teams=value.teams;teamFiles=value.teamFiles;facilities=value.facilities;facilityFiles=value.facilityFiles;baseline=value.baseline;speed=value.speed;facebookUrl=value.facebookUrl;
                [['[data-ve-sport]',rows,'data-ve-sport',add],['[data-ve-team]',teams,'data-ve-team',addTeam],['[data-ve-facility]',facilities,'data-ve-facility',addFacility]].forEach(function(group){
                    $$(group[0],S.doc).forEach(function(node){if(!group[1].some(function(row){return row.id===node.getAttribute(group[2]);}))node.remove();});
                    group[1].slice().forEach(function(row){if(!S.doc.querySelector('['+group[2]+'="'+cssEsc(row.id)+'"]'))group[3](row);});
                });paintSpeed();
            },
            draftRebind:function(){$$('[data-ve-sport]',S.doc).forEach(bindSport);$$('[data-ve-team]',S.doc).forEach(bindTeam);$$('[data-ve-facility]',S.doc).forEach(bindFacility);marquee();refreshFacilities();},
            mount:function(doc){
                var block=doc.getElementById('veSportsData');data=block?JSON.parse(block.textContent):null;
                facebookUrl=data?data.content.facebook_url||'':'';
                rows=data?JSON.parse(JSON.stringify(data.content.sports)):[];texts=data?Object.assign({},data.content.texts):{};files={};backgroundFiles={};preparing=0;speed=data&&data.content.speed!=null?Number(data.content.speed):1;baseline=snapshot();
                teams=data?JSON.parse(JSON.stringify(data.content.teams||[])):[];teamFiles={};baseline=snapshot();
                facilities=data?JSON.parse(JSON.stringify(data.content.facilities||[])):[];facilityFiles={};baseline=snapshot();
                var range=doc.querySelector('[data-ve-sports-speed]'),slower=doc.querySelector('[data-ve-sports-slower]'),faster=doc.querySelector('[data-ve-sports-faster]');
                if(range)range.addEventListener('input',function(){setSpeed(range.value);});
                if(slower)slower.addEventListener('click',function(){setSpeed(speed-0.25);});
                if(faster)faster.addEventListener('click',function(){setSpeed(speed+0.25);});
                var motionButton=doc.querySelector('[data-ve-sports-motion]');
                if(motionButton)motionButton.addEventListener('click',function(){if(!S.preview&&!S.busy)setMotion(!motion);});
                paintSpeed();setMotion(true);
                $$('[data-ve-field="sports-text"]',doc).forEach(function(el){makeField(el,function(node,value){texts[node.getAttribute('data-ve-key')]=value;});});
                $$('[data-ve-sport]',doc).forEach(bindSport);$$('[data-ve-team]',doc).forEach(bindTeam);$$('[data-ve-facility]',doc).forEach(bindFacility);refreshFacilities();return false;
            },
            pick:function(target){
                if(target.closest('[data-ve-sports-facebook]')){editFacebook();return {kind:'none'};}
                if(target.closest('[data-ve-facility-add]')){addFacility();return {kind:'none'};}
                var facilityUpload=target.closest('[data-ve-facility-upload]');if(facilityUpload){replaceFacility(facilityUpload.closest('[data-ve-facility]'));return {kind:'none'};}
                if(target.closest('.athletics-facilities-controls'))return {kind:'facility-controls',el:target.closest('.athletics-facilities-controls'),prevent:false};
                if(target.closest('[data-ve-team-add]')){addTeam();return {kind:'none'};}
                var teamUpload=target.closest('[data-ve-team-upload]');if(teamUpload){replaceTeam(teamUpload.closest('[data-ve-team]'));return {kind:'none'};}
                if(target.closest('[data-ve-sports-speed-controls]'))return {kind:'sports-speed',el:target.closest('[data-ve-sports-speed-controls]'),prevent:false};
                var background=target.closest('[data-ve-sports-background]');
                if(background){replaceBackground(background.getAttribute('data-ve-sports-background'));return {kind:'none'};}
                if(target.closest('[data-ve-sport-add]')){add();return {kind:'none'};}
                var field=fieldOf(target);if(field&&field.__ve){if(field.closest('[data-ve-sport]'))setMotion(false);return {kind:'field',el:field,prevent:false};}
                var facilityFigure=target.closest('[data-ve-facility]');if(facilityFigure)return {kind:'facility',el:facilityFigure};
                var figure=target.closest('[data-ve-team]');if(figure)return {kind:'team-logo',el:figure};
                var card=target.closest('[data-ve-sport]');if(card){setMotion(false);return {kind:'sport',el:card};}
                var area=target.closest('[data-ve-sports-background-area]');return area?{kind:'sports-background',el:area,key:area.getAttribute('data-ve-sports-background-area'),anchor:'image'}:null;
            },
            toolbar:function(sel){
                if(sel.kind==='facility-controls')return [];
                var facility=facilityOf(sel.el);
                if(facility){var figure=sel.el.closest('[data-ve-facility]'),index=facilities.indexOf(facility);return [
                    {type:'chip',text:facility.name,icon:'building'},
                    {icon:'image',label:'Change photo',primary:true,run:function(){replaceFacility(figure);}},
                    {icon:'plus-circle',label:'Add facility photo',run:addFacility},
                    {icon:'arrow-left',title:'Move facility earlier',disabled:index===0,run:function(){moveFacility(figure,-1);}},
                    {icon:'arrow-right',title:'Move facility later',disabled:index===facilities.length-1,run:function(){moveFacility(figure,1);}},
                    {icon:'trash',label:'Remove facility photo',danger:true,run:function(){confirmPop('Remove facility photo?','The photo will be removed when you Publish.','Remove photo',function(){facilities=facilities.filter(function(item){return item!==facility;});delete facilityFiles[facility.id];figure.remove();select(null);closePop();refreshFacilities();chrome();},true);}}
                ];}
                var team=teamOf(sel.el);
                if(team){var figure=sel.el.closest('[data-ve-team]'),index=teams.indexOf(team);return [
                    {type:'chip',text:team.name,icon:'shield'},
                    {icon:'image',label:'Change logo',primary:true,run:function(){replaceTeam(figure);}},
                    {icon:'plus-circle',label:'Add logo',run:addTeam},
                    {icon:'arrow-left',title:'Move logo earlier',disabled:index===0,run:function(){moveTeam(figure,-1);}},
                    {icon:'arrow-right',title:'Move logo later',disabled:index===teams.length-1,run:function(){moveTeam(figure,1);}},
                    {icon:'trash',label:'Remove logo',danger:true,run:function(){confirmPop('Remove team logo?','The logo will be removed when you Publish.','Remove logo',function(){teams=teams.filter(function(item){return item!==team;});delete teamFiles[team.id];figure.remove();select(null);closePop();chrome();},true);}}
                ];}
                if(sel.kind==='sports-speed')return [{type:'chip',text:'Slideshow speed: '+speed+'×',icon:'speedometer2'}];
                if(sel.kind==='sports-background')return [{icon:'image',label:'Change background image',primary:true,run:function(){replaceBackground(sel.key);}}];
                var row=rowOf(sel.el),card=sel.el&&sel.el.closest('[data-ve-sport]');
                if(!row)return [{type:'chip',text:'Edit text',icon:'pencil'}];
                var index=rows.indexOf(row);
                return [
                    {type:'chip',text:row.name,icon:'dribbble'},
                    {icon:'image',label:'Change photo',primary:true,run:function(){replace(card);}},
                    {icon:'arrow-left',title:'Move earlier',disabled:index===0,run:function(){move(card,-1);}},
                    {icon:'arrow-right',title:'Move later',disabled:index===rows.length-1,run:function(){move(card,1);}},
                    {icon:'trash',label:'Remove sport',danger:true,run:function(){confirmPop('Remove sport?','This sport will be removed when you Publish.','Remove sport',function(){rows=rows.filter(function(item){return item!==row;});delete files[row.id];card.remove();select(null);closePop();marquee();chrome();},true);}}
                ];
            },
            dirtyCount:dirty,
            status:function(){var n=dirty();return {text:preparing?'Preparing image upload...':n?plural(n,'unsaved change'):'Click a sport to edit it, or add another sport.',tone:n?'warn':'ok'};},
            actions:function(){return {secondary:{label:'Add sport',icon:'plus-circle',disabled:!data||rows.length>=30},primary:{label:'Publish',icon:'cloud-upload',disabled:!data||!dirty()||preparing>0}};},
            runSecondary:add,
            runPrimary:function(){
                if(!data||!dirty()||preparing)return;
                confirmPublish('Publish the sports slideshow and page changes?',function(){run('Publishing sports...',function(){
                    var uploads={};Object.keys(files).forEach(function(id){uploads['sport_'+id]=files[id];});
                    Object.keys(backgroundFiles).forEach(function(key){uploads['background_'+key]=backgroundFiles[key];});
                    Object.keys(teamFiles).forEach(function(id){uploads['team_'+id]=teamFiles[id];});
                    Object.keys(facilityFiles).forEach(function(id){uploads['facility_'+id]=facilityFiles[id];});
                    return send('/admin/action/athletics-sports-handler.php',{content:JSON.stringify(payload()),revision:data.revision},uploads).then(function(result){
                        if(!result.ok){toast(result.error,'error');return;}
                        files={};backgroundFiles={};teamFiles={};facilityFiles={};baseline=snapshot();reloadEditor('Published. Your changes are live now.');
                    });
                });});
            },
            onLink:function(link){
                var url=new URL(link.getAttribute('href'),S.win.location.href);
                if(url.origin===window.location.origin && /\/athletics-sports\.php$/.test(url.pathname) && url.searchParams.has('highlights_page')){
                    if(S.adapter.dirtyCount()){toast('Publish or cancel your changes before switching highlights pages.','error');return true;}
                    loadFrame(url.pathname+url.search+url.hash,true);return true;
                }
                return false;
            }
        };
    }

    function FaqAdapter() {
        var content=null,baseline='',revision='';
        function dirty(){return content&&JSON.stringify(content)!==baseline?1:0;}
        function bind(){
            $$('.faq-item summary',S.doc).forEach(function(summary){if(summary.__faqBound)return;summary.__faqBound=true;summary.addEventListener('click',function(event){if(!S.preview)event.preventDefault();});});
            $$('[data-ve-field^="faq-"]',S.doc).forEach(function(el){
                var kind=el.getAttribute('data-ve-field'),key=el.getAttribute('data-ve-key');
                el.setAttribute('data-ve-ph',el.getAttribute('data-ve-label')||'Click to edit');
                makeField(el,function(node,value){
                    if(kind==='faq-text')content.texts[key]=value;
                    else{var item=content.items.find(function(row){return row.id===key;});if(item)item[kind==='faq-question'?'question':'answer']=value;}
                });
            });
        }
        function field(tag,kind,row,key,label,max){
            var el=S.doc.createElement(tag);el.textContent=row[key];
            el.setAttribute('data-ve-field',kind);el.setAttribute('data-ve-key',row.id);el.setAttribute('data-ve-label',label);el.setAttribute('data-ve-max',max);
            if(key==='answer')el.setAttribute('data-ve-multiline','1');return el;
        }
        function render(){
            if(!content)return;
            var list=S.doc.querySelector('[data-faq-list]'),markers={};
            $$('[data-gnc-text]',list).forEach(function(el){markers[el.getAttribute('data-ve-field')+':'+el.getAttribute('data-ve-key')]=el.getAttribute('data-gnc-text');});
            list.replaceChildren();
            content.items.forEach(function(row){
                var details=S.doc.createElement('details');details.className='faq-item';details.open=true;details.setAttribute('data-ve-faq-item',row.id);
                var summary=S.doc.createElement('summary'),chevron=S.doc.createElement('i');chevron.className='bi bi-chevron-down';chevron.setAttribute('aria-hidden','true');
                summary.append(field('span','faq-question',row,'question','Question',1000),chevron);
                var answer=field('div','faq-answer',row,'answer','Answer',20000);answer.className='faq-answer';details.append(summary,answer);list.append(details);
            });
            $$('[data-ve-field]',list).forEach(function(el){var marker=markers[el.getAttribute('data-ve-field')+':'+el.getAttribute('data-ve-key')];if(marker)el.setAttribute('data-gnc-text',marker);});
            list.hidden=!content.items.length;S.doc.querySelector('[data-faq-empty]').hidden=!!content.items.length;bind();
        }
        function add(){
            if(!content||S.busy)return;
            if(content.items.length>=200){toast('This page supports up to 200 FAQs.','error');return;}
            var id='faq-new-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10);
            content.items.push({id:id,question:'',answer:''});select(null);render();chrome();
            var target=S.doc.querySelector('[data-ve-faq-item="'+id+'"] [data-ve-field="faq-question"]');target.scrollIntoView({block:'center'});target.focus();
        }
        function move(id,step){
            var index=content.items.findIndex(function(row){return row.id===id;}),next=index+step;
            if(index<0||next<0||next>=content.items.length)return;
            var item=content.items.splice(index,1)[0];content.items.splice(next,0,item);select(null);render();chrome();
        }
        return {
            mount:function(doc){content=null;baseline='';revision='';var source=doc.getElementById('faqPageData');if(source){var data=JSON.parse(source.textContent);content=data.content;revision=data.revision;baseline=JSON.stringify(content);bind();}return false;},
            draftState:function(value){if(!value)return {content:content,baseline:baseline,revision:revision};content=value.content;baseline=value.baseline;revision=value.revision;render();},
            draftRebind:render,
            pick:function(target){
                if(target.closest('[data-ve-faq-add]')){add();return {kind:'none'};}
                var el=fieldOf(target);if(el&&el.__ve)return {kind:'field',el:el,prevent:false};
                var item=target.closest('[data-ve-faq-item]');return item?{kind:'faq-item',el:item,id:item.getAttribute('data-ve-faq-item'),prevent:true}:null;
            },
            toolbar:function(sel){
                var item=sel.el.closest('[data-ve-faq-item]');if(!item)return [];
                var id=item.getAttribute('data-ve-faq-item'),index=content.items.findIndex(function(row){return row.id===id;});
                return [{type:'chip',icon:'question-circle',text:'FAQ'},
                    {icon:'arrow-up',title:'Move FAQ earlier',disabled:index===0,run:function(){move(id,-1);}},
                    {icon:'arrow-down',title:'Move FAQ later',disabled:index===content.items.length-1,run:function(){move(id,1);}},
                    {icon:'trash',title:'Delete FAQ',danger:true,run:async function(){if(!await window.AdminDialogs.confirm('Delete this FAQ? It will be removed from the website when you publish.'))return;content.items=content.items.filter(function(row){return row.id!==id;});select(null);render();chrome();}}];
            },
            dirtyCount:dirty,
            status:function(){return {text:dirty()?'You have unpublished FAQ changes.':content?'Click a question or answer to edit.':'Install the FAQ migration to start editing.',tone:dirty()?'warn':'ok'};},
            actions:function(){return {secondary:{label:'Add FAQ',icon:'plus-lg',disabled:!content},primary:{label:'Publish',icon:'cloud-upload',disabled:!dirty()}};},
            runSecondary:add,
            runPrimary:function(){if(!dirty())return;confirmPublish('Publish FAQ changes?',function(){run('Publishing FAQs...',async function(){
                var result=await send('/admin/action/faqs-handler.php',{content:JSON.stringify(content),revision:revision});
                if(!result.ok){toast(result.error,'error');return;}
                baseline=JSON.stringify(content);revision=result.revision;reloadEditor('Published. Your changes are live now.',{page:'faqs'},true);
            });});}
        };
    }

    function CommunityActivitiesAdapter() {
        var content=null,baseline='',revision='',files={},api=null,limits={};
        function dirty(){return content&&(JSON.stringify(content)!==baseline||Object.keys(files).length)?1:0;}
        function activity(id){return content&&content.activities.find(function(row){return row.id===id;});}
        function bind(){
            if(!content)return;
            $$('[data-ve-field^="community-"]',S.doc).forEach(function(el){
                var kind=el.getAttribute('data-ve-field'),key=el.getAttribute('data-ve-key');
                el.setAttribute('data-ve-ph',el.getAttribute('data-ve-label')||'Click to edit');
                makeField(el,function(node,value){
                    if(kind==='community-text'){
                        content.texts[key]=value;
                        if(key==='hero_title')S.doc.querySelector('[data-community-breadcrumb]').textContent=value;
                    }else{
                        var row=activity(key),property=kind.slice('community-activity-'.length);
                        if(row)row[property]=property==='description'?value:String(node.textContent||'').replace(/\s+/g,' ').trim();
                    }
                });
            });
        }
        function render(){
            if(!api||!content)return;
            api.render(content);
            var hero=S.doc.querySelector('.community-activity-hero');
            if(hero)hero.style.backgroundImage='url('+JSON.stringify(content.hero_image)+')';
            var closing=S.doc.querySelector('[data-community-closing-image] img');
            if(closing&&content.closing_image)closing.src=content.closing_image;
            bind();
        }
        function add(){
            if(!content||S.busy)return;
            if(content.activities.length>=100){toast('This page supports up to 100 activities.','error');return;}
            var id='activity-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10);
            content.activities.push({id:id,title:'',description:'',category:'',image:''});
            select(null);render();api.select(id);chrome();
            var target=S.doc.querySelector('[data-ve-community-card="'+cssEsc(id)+'"] h4');
            if(target){target.scrollIntoView({block:'center'});target.focus();}
        }
        function replace(id){
            if(!content||['hero','closing','showcase'].indexOf(id)<0)return;
            var target=id==='hero'?S.doc.querySelector('.community-activity-hero'):id==='closing'?S.doc.querySelector('[data-community-closing-image]'):S.doc.querySelector('[data-community-feature]');
            pickFile('image/jpeg,image/png,image/webp,image/gif',function(file){
                if(file.size>5*MB||!/^image\/(jpeg|png|webp|gif)$/.test(file.type)){toast('Choose a JPG, PNG, WebP or GIF image of up to 5 MB.','error');return;}
                files[id+'_image']=file;
                content[id+'_image']=previewUrl(file);
                if(S.sel&&S.sel.el&&S.sel.el.closest('[data-ve-community-card]'))select(null);
                render();chrome();renderToolbar();
            },false,target);
        }
        function move(id,step){
            var index=content.activities.findIndex(function(row){return row.id===id;}),next=index+step;
            if(index<0||next<0||next>=content.activities.length)return;
            var item=content.activities.splice(index,1)[0];content.activities.splice(next,0,item);
            select(null);render();chrome();
        }
        function activityActions(id){return [
            {icon:'arrow-up',title:'Move activity earlier',run:function(){move(id,-1);}},
            {icon:'arrow-down',title:'Move activity later',run:function(){move(id,1);}},
            {icon:'trash',title:'Delete activity',danger:true,run:async function(){
                if(!await window.AdminDialogs.confirm('Delete this activity? It will be removed from the public page when you publish.'))return;
                content.activities=content.activities.filter(function(row){return row.id!==id;});delete files['image_'+id];select(null);render();chrome();
            }}
        ];}
        return {
            draftState:function(value){
                if(!value)return {content:content,baseline:baseline,revision:revision,files:files};
                content=value.content;baseline=value.baseline;revision=value.revision;files=value.files||{};
                if(typeof content.showcase_image!=='string')content.showcase_image=(content.activities.find(function(row){return row.image;})||{}).image||'';
                render();
            },
            draftRebind:render,
            mount:function(doc){
                content=null;files={};baseline='';revision='';api=doc.defaultView.GNCCommunityActivities;
                try{var data=JSON.parse(doc.getElementById('communityPageData').textContent);content=data.content;content.showcase_image=data.showcase_image||'';limits=data.limits;revision=data.revision;baseline=JSON.stringify(content);}catch(_){}
                bind();return false;
            },
            pick:function(target){
                if(target.closest('[data-ve-community-add]')){add();return {kind:'none'};}
                var post=postPick(target);if(post)return post;
                var field=fieldOf(target);if(field&&field.__ve)return {kind:'field',el:field,prevent:false};
                var image=target.closest('[data-ve-community-image]');
                if(image&&content)return {kind:'community-image',el:image,id:image.getAttribute('data-ve-community-image'),prevent:true};
                var card=target.closest('[data-ve-community-card]');
                if(card&&content){api.select(card.getAttribute('data-ve-community-card'));return {kind:'community-card',el:card,id:card.getAttribute('data-ve-community-card'),prevent:true};}
                return null;
            },
            toolbar:function(sel){
                if(sel.kind==='post')return postToolbar(sel);
                if(sel.kind==='community-image')return [{icon:'image',label:'Replace picture',primary:true,run:function(){replace(sel.id);}}];
                if(sel.kind==='community-card')return activityActions(sel.id);
                if(sel.kind==='field'){
                    var card=sel.el.closest('[data-ve-community-card]');
                    return [{type:'chip',icon:'pencil',text:sel.el.__ve.label}].concat(card?activityActions(card.getAttribute('data-ve-community-card')):[]);
                }
                return [];
            },
            onLink:function(link){
                if(!link.closest('#community-impact .campus-recent-pagination'))return false;
                var url=new URL(link.getAttribute('href'),new URL(S.url,window.location.origin));
                (async function(){if(await guardDirty())loadFrame(withParam(url.pathname+url.search+url.hash,'ce_preview','1'));})();
                return true;
            },
            hasPageOptions:function(){return !!content;},
            pageOptions:function(x,y){
                var box=h('div',{class:'ve-pop-form'},[h('strong',{text:'Community activity page'}),h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Replace banner image',on:{click:function(){replace('hero');}}})]);
                ['empty_title','empty_description'].forEach(function(key){
                    var label=key==='empty_title'?'Empty-state heading':'Empty-state description';
                    var input=h(key==='empty_description'?'textarea':'input',{class:'form-control form-control-sm','aria-label':label,maxlength:limits[key]});input.value=content.texts[key];
                    input.addEventListener('input',function(){content.texts[key]=input.value;var target=S.doc.querySelector('[data-ve-field="community-text"][data-ve-key="'+key+'"]');if(target)target.textContent=input.value;chrome();});
                    box.appendChild(h('label',{class:'ve-pop-label',text:label},[input]));
                });openPop(box,x,y);
            },
            dirtyCount:dirty,
            status:function(){return {text:dirty()?'You have unpublished activity changes.':content?'Click text or a picture to edit. Add activities when ready.':'Install the community activities migration to edit this page.',tone:dirty()?'warn':'ok'};},
            actions:function(){return {secondary:{label:'Add activity',icon:'plus-lg',disabled:!content},primary:{label:'Publish',icon:'cloud-upload',disabled:!dirty()}};},
            runSecondary:add,
            runPrimary:function(){
                if(!dirty())return;
                confirmPublish('Publish community activity changes?',function(){run('Publishing…',async function(){
                    if(!S.allowIncomplete&&content.activities.some(function(row){return !row.title.trim()||!row.description.trim()||!row.category.trim();})){toast('Each activity needs a title, description and category.','error');return;}
                    var response=await send('/admin/action/community-activities-handler.php',{content:JSON.stringify(content),revision:revision},files);
                    if(!response.ok){toast(response.error,'error');return;}
                    files={};baseline=JSON.stringify(content);revision=response.revision;reloadEditor('Published. Your changes are live now.',{page:'community-activities'},true);
                });});
            }
        };
    }

    function PaymentGuideAdapter() {
        var data, changes = {}, texts = {}, urls = [];
        function dirty() { return Object.keys(changes).length + Object.keys(texts).length; }
        function bindFields(doc) {
            $$('[data-ve-field="payment-guide-text"]', doc).forEach(function (el) {
                var key = el.getAttribute('data-ve-key'), field = data.fields[key];
                if (!field) return;
                makeField(el, function (node, value) {
                    if (value === field.value) delete texts[key]; else texts[key] = value;
                    // Account details appear in both payment options and share one saved value.
                    $$('[data-ve-field="payment-guide-text"]', doc).forEach(function (other) {
                        if (other !== node && other.getAttribute('data-ve-key') === key) {
                            if (field.type === 'rich') other.innerHTML = node.innerHTML; else other.textContent = value;
                        }
                    });
                });
            });
        }
        function editDestination(link) {
            var key = link.getAttribute('data-ve-payment-link'), field = data.fields[key];
            askText({label:field.label, value:Object.prototype.hasOwnProperty.call(texts,key) ? texts[key] : field.value, max:field.max,
                hint:'Use an https:// or http:// address, or a local path starting with /. Changes go live when you publish.',
                done:function (value) {
                    var valid = value === '' || (!/[\s\x00-\x1f\x7f<>"\\]/.test(value) && (/^\/(?!\/)/.test(value) || /^https?:\/\//i.test(value)));
                    if (valid && value && !value.startsWith('/')) {
                        try { var url = new URL(value); valid = !!url.hostname; } catch (_) { valid = false; }
                    }
                    if (!valid) { toast('Enter a valid HTTP or HTTPS address, or a local path starting with /.','error'); return; }
                    if (value === field.value) delete texts[key]; else texts[key] = value;
                    link.setAttribute('href',value); chrome(); renderToolbar();
                }
            });
        }
        function imageOf(el) { return el.querySelector('img'); }
        function restore(el, key) {
            imageOf(el).src = data.images[key].default;
            if (data.images[key].url === data.images[key].default) delete changes[key];
            else changes[key] = {reset:true};
            chrome(); renderToolbar();
        }
        function replace(el, key) {
            pickFile('image/svg+xml,image/png,image/jpeg,image/webp,.svg,.png,.jpg,.jpeg,.webp', function (file) {
                if (!/\.(svg|png|jpe?g|webp)$/i.test(file.name) || file.size > 5 * MB) {
                    toast('Choose an SVG, PNG, JPG, or WEBP image of up to 5 MB.', 'error'); return;
                }
                var url = previewUrl(file); urls.push(url);
                changes[key] = {reset:false, file:file}; imageOf(el).src = url;
                chrome(); renderToolbar();
            });
        }
        function browse() {
            toast('On Storyset, choose Download SVG. Download illustrations in .svg format before uploading them here.', 'ok');
            window.open('https://storyset.com/', '_blank', 'noopener,noreferrer');
        }
        return {
            draftState:function(value){if(!value)return {changes:changes,texts:texts};changes=value.changes;texts=value.texts;},
            mount:function (doc) {
                urls.forEach(function (url) { URL.revokeObjectURL(url); }); urls = []; changes = {}; texts = {}; data = null;
                try { data = JSON.parse(doc.getElementById('vePaymentGuideData').textContent); } catch (_) {}
                if (data && data.fields) bindFields(doc);
                return false;
            },
            pick:function (target) {
                var field = fieldOf(target);
                if (field && field.__ve) return {kind:'field',el:field};
                var link = target.closest('[data-ve-payment-link]');
                if (link && data) return {kind:'payment-guide-link',el:link};
                var el = target.closest('[data-ve-img="payment-guide-image"]');
                return el && data ? {kind:'payment-guide-image', el:el, key:el.getAttribute('data-ve-key')} : null;
            },
            toolbar:function (sel) {
                if (sel.kind === 'field' || sel.kind === 'payment-guide-link') {
                    var link = sel.el.closest('[data-ve-payment-link]');
                    var items = [{type:'chip',text:sel.kind === 'field' ? sel.el.__ve.label : 'Button destination',icon:'pencil'}];
                    if (link) items.push({icon:'link-45deg',label:'Edit destination',run:function () { editDestination(link); }});
                    return items;
                }
                if (sel.kind !== 'payment-guide-image') return [];
                return [
                    {type:'chip', text:data.images[sel.key].label, icon:'image'},
                    {icon:'image', label:'Change illustration', primary:true, run:function () { replace(sel.el, sel.key); }},
                    {icon:'arrow-counterclockwise', label:'Restore Storyset illustration', run:function () { restore(sel.el, sel.key); }},
                    {icon:'box-arrow-up-right', label:'Browse Storyset', run:browse}
                ];
            },
            dirtyCount:dirty,
            status:function () { return {text:dirty() ? plural(dirty(),'unsaved change') : 'Click text, a button, or an illustration to edit. Publish when ready.', tone:dirty()?'warn':'ok'}; },
            actions:function () { return {secondary:{label:'Browse Storyset',icon:'box-arrow-up-right'}, primary:{label:'Publish',icon:'cloud-upload',disabled:!data || !dirty()}}; },
            runSecondary:browse,
            runPrimary:function () {
                confirmPublish('Publish payment guide changes?', function () {
                    run('Publishing payment guide...', function () {
                        var payload = {}, files = {};
                        Object.keys(changes).forEach(function (key) {
                            payload[key] = {reset:changes[key].reset};
                            if (changes[key].file) files['image_'+key] = changes[key].file;
                        });
                        return send('/admin/action/payment-guide-handler.php', {images:JSON.stringify(payload),texts:JSON.stringify(texts)}, files).then(function (result) {
                            if (!result.ok) { toast(result.error,'error'); return; }
                            changes = {}; texts = {}; reloadEditor('Published. Your changes are live now.');
                        });
                    });
                });
            },
            onLink:function () { return false; }
        };
    }
    function TuitionAdapter() {
        var data, api, texts = {}, changes = {}, serial = 0, urls = [];
        function own(object,key) { return Object.prototype.hasOwnProperty.call(object,key); }
        function dirty() { return Object.keys(texts).length + Object.keys(changes).length; }
        function chooseIcon(sel) {
            var field = data.fields[sel.key];
            libraryIconPicker(data.icons, own(texts, sel.key) ? texts[sel.key] : field.value, function (spec, choice) {
                if (spec === field.value) delete texts[sel.key]; else texts[sel.key] = spec;
                var node = S.doc.createElement(choice.material ? 'span' : 'i');
                node.setAttribute('aria-hidden', 'true');
                node.className = choice.material ? 'material-symbols-outlined' : 'bi bi-' + choice.glyph;
                if (choice.material) node.textContent = choice.material;
                sel.el.replaceChildren(node);
            });
        }
        function bindFields() {
            $$('[data-ve-field="tuition-text"]', S.doc).forEach(function (el) {
                var key = el.getAttribute('data-ve-key'), field = data.fields[key];
                if (!field) return;
                if (own(texts,key)) { if (field.max>255) el.innerHTML = texts[key]; else el.textContent = texts[key]; }
                makeField(el, function (node,value) {
                    if (value===field.value) delete texts[key]; else texts[key]=value;
                    if (key.indexOf('category_')===0) api.categories[key.slice(9)].label=value;
                });
            });
        }
        function refresh() { api.refresh(); bindFields(); chrome(); }
        function remember(doc, category) {
            var key = doc._key || ('doc_'+doc.id);
            changes[key] = Object.assign(changes[key] || {}, {id:doc.id||0,category:category,title:doc.title});
            return changes[key];
        }
        function stage(file, existing, category) {
            if (!/\.pdf$/i.test(file.name) || file.size>20*MB) { toast('Choose a PDF of up to 20 MB.','error'); return; }
            var url = previewUrl(file); urls.push(url);
            var doc = existing || {id:0,_key:'new_'+(++serial),title:file.name.replace(/\.pdf$/i,'')};
            doc.url=url; doc.size=Math.max(1,Math.round(file.size/1024))+' KB';
            if (!existing) api.categories[category].documents.push(doc);
            remember(doc,category).file=file;
            refresh(); manage();
        }
        function manage(x,y) {
            if (!api) return;
            if(typeof x!=='number'||typeof y!=='number'){var rect=btnSecondary.getBoundingClientRect();x=rect.left;y=rect.bottom+8;}
            var category=api.selection().category, records=api.categories[category].documents;
            var box=h('div',{class:'ve-pop-form'},[
                h('strong',{text:api.categories[category].label+' PDFs'}),
                h('p',{class:'small text-muted',text:'Upload, rename, replace or remove schedules. Changes go live when you publish.'})
            ]);
            records.forEach(function(doc,index){
                var title=h('input',{type:'text',class:'form-control form-control-sm',value:doc.title,maxlength:'255','aria-label':'PDF document title',on:{input:function(){doc.title=title.value;remember(doc,category);refresh();}}});
                var row=h('div',{style:'border:1px solid #dce4dd;border-radius:10px;padding:12px;margin-bottom:10px'},[
                    title,h('div',{style:'display:flex;gap:8px;margin-top:8px;flex-wrap:wrap'},[
                        h('button',{type:'button',class:'btn btn-sm btn-outline-success',text:'View',on:{click:function(){api.show(category,index);bindFields();closePop();}}}),
                        h('button',{type:'button',class:'btn btn-sm btn-outline-secondary',text:'Replace',on:{click:function(){pickFile('application/pdf',function(file){stage(file,doc,category);});}}}),
                        h('button',{type:'button',class:'btn btn-sm btn-outline-danger',text:'Remove',on:{click:async function(){
                            if(!await window.AdminDialogs.confirm('Remove this PDF when you publish?'))return;
                            if(doc.id) remember(doc,category).remove=true; else delete changes[doc._key];
                            records.splice(records.indexOf(doc),1);refresh();manage(x,y);
                        }}})
                    ])
                ]);box.appendChild(row);
            });
            box.appendChild(h('button',{type:'button',class:'btn btn-sm btn-success',text:'Add PDF',on:{click:function(){pickFile('application/pdf',function(file){stage(file,null,category);});}}}));
            openPop(box,x,y);
        }
        return {
            draftState:function(value){if(!value)return {texts:texts,changes:changes,serial:serial,categories:api&&api.categories};texts=value.texts;changes=value.changes;serial=value.serial;if(api&&value.categories){Object.keys(value.categories).forEach(function(key){Object.assign(api.categories[key],value.categories[key]);});refresh();}},
            draftRebind:function(){bindFields();},
            mount:function(doc){
                urls.forEach(function(url){URL.revokeObjectURL(url);});urls=[];texts={};changes={};
                try {data=JSON.parse(doc.getElementById('veTuitionData').textContent);api=doc.defaultView.GNCTuitionFees;}catch(_){data=null;}
                if(!data||!api)return false;
                bindFields();doc.addEventListener('tuition:changed',bindFields);return false;
            },
            pick:function(target,event){
                if(target.closest('[data-ve-fee-manage]')){manage(event.clientX,event.clientY);return {kind:'none'};}
                var icon = target.closest('[data-ve-icon="tuition"]');
                if (icon) return {kind:'tuition-icon',el:icon,key:icon.getAttribute('data-ve-key')};
                var field=fieldOf(target);if(field&&field.__ve)return {kind:'field',el:field};
                var category=target.closest('[data-fee-category]');
                if(category){event.stopImmediatePropagation();select(null);api.show(category.dataset.feeCategory);bindFields();return {kind:'none'};}
                var cover=target.closest('[data-ve-fee-pdf]');if(cover)return {kind:'tuition-pdf',el:cover};
                return null;
            },
            toolbar:function(sel){
                if (sel.kind==='tuition-icon') return [{icon:'grid',label:'Change icon',primary:true,run:function(){chooseIcon(sel);}}];
                return sel.kind==='tuition-pdf'?[{icon:'file-earmark-pdf',label:'Manage PDFs',primary:true,run:manage}]:[];
            },
            dirtyCount:dirty,
            status:function(){return {text:dirty()?plural(dirty(),'unsaved change'):'Everything here is live',tone:dirty()?'warn':'ok'};},
            actions:function(){return {secondary:{label:'Manage PDFs',icon:'file-earmark-pdf',disabled:!api},primary:{label:'Publish',icon:'cloud-upload',disabled:!data||!dirty()}};},
            runSecondary:function(){manage();},
            runPrimary:function(){
                confirmPublish('Publish tuition and fee changes?',function(){run('Publishing…',function(){
                    var list=Object.keys(changes).map(function(key){return changes[key];}),files={};
                    list.forEach(function(change,index){if(change.file&&!change.remove)files['pdf_'+index]=change.file;});
                    var payload=list.map(function(change){return {id:change.id,category:change.category,title:change.title,remove:!!change.remove};});
                    return send('/admin/action/tuition-fees-handler.php',{texts:JSON.stringify(texts),documents:JSON.stringify(payload)},files).then(function(result){
                        if(!result.ok){toast(result.error,'error');return;}
                        texts={};changes={};reloadEditor('Published. Your changes are live now.');
                    });
                });});
            },
            onLink:function(link){
                if(link.hasAttribute('data-fee-category')){api.show(link.dataset.feeCategory);return true;}
                return false;
            }
        };
    }
    function FeedAdapter() {
        var A = {
            mount: function () { return false; },
            pick: function (t) { return postPick(t); },
            toolbar: function (sel) { return sel.kind === 'post' ? postToolbar(sel) : []; },
            onLink: function () { return false; },
            dirtyCount: function () { return 0; },
            status: function () { return { text: 'Click a news item, announcement or event to edit it here.', tone: 'ok' }; },
            actions: function () { return { secondary: null, primary: null }; }
        };
        return A;
    }

    // ══════════════════ side panel: the existing managers, opened only when needed ══════════════════
    var drawer = $('#veDrawer');
    var drawerBody = $('#veDrawerBody'), embedWrap = $('#veEmbedWrap'), embedFrame = $('#veEmbed'), drawerTitle = $('#veDrawerTitle');
    var drawerTitleDefault = drawerTitle ? drawerTitle.textContent : '', embedOpen = false;
    /** The side panel shows either the page's own settings form or one of the post editors (News, Announcements, Events). */
    function showPane(embed) {
        embedOpen = !!embed;
        if (drawerBody) drawerBody.hidden = !!embed;
        if (embedWrap) embedWrap.hidden = !embed;
        if (drawerTitle) drawerTitle.textContent = embed ? (drawerTitle.getAttribute('data-embed-title') || 'News, announcements and events') : drawerTitleDefault;
        if (drawer) drawer.classList.toggle('is-embed', !!embed);
    }
    function openDrawer() {
        if (!drawer || !drawerBody) { toast('There is nothing more to set up on this page.', 'ok'); return; }
        showPane(false);
        drawer.hidden = false; D.body.classList.add('ve-drawer-open'); closePop();
    }
    function openEmbed(url, title) {
        if (!drawer || !embedWrap || !embedFrame) { toast('News, announcements and events cannot be opened from here for your account.', 'error'); return; }
        if (drawerTitle) drawerTitle.setAttribute('data-embed-title', title || '');
        showPane(true);
        embedFrame.src = url;
        drawer.hidden = false; D.body.classList.add('ve-drawer-open'); closePop();
    }
    function closeDrawer() {
        if (!drawer || drawer.hidden) return;
        var wasEmbed = embedOpen;
        drawer.hidden = true; D.body.classList.remove('ve-drawer-open');
        if (wasEmbed && embedFrame) embedFrame.src = 'about:blank';
        if (S.adapter.dirtyCount() === 0) loadFrame(S.url, true);
        else if (wasEmbed) toast('Posts you saved are live. Publish or cancel your edits on this page, then the preview shows them.', 'ok');
    }
    if (drawer) {
        $$('[data-ve-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });
        D.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden && !$('.modal.show')) closeDrawer(); });
    }

    // ══════════════════ start ══════════════════
    var kind = C.page.kind;
    S.adapter = kind === 'faqs' ? FaqAdapter() : kind === 'community' ? CommunityActivitiesAdapter() : kind === 'sports' ? SportsAdapter() : kind === 'payment-guide' ? PaymentGuideAdapter() : kind === 'achievements' ? AchievementAdapter() : kind === 'tuition' ? TuitionAdapter() : kind === 'organizations' ? OrganizationAdapter() : kind === 'home' ? HomeAdapter() : kind === 'admin' ? AdminAdapter() : kind === 'sections' ? SectionsAdapter() : kind === 'feed' ? FeedAdapter() : EntityAdapter();
    function withImagePositions(adapter) {
        var dirty = adapter.dirtyCount, actions = adapter.actions, status = adapter.status, primary = adapter.runPrimary;
        adapter.dirtyCount = function () { return dirty.call(adapter) + displayPlacementCount(); };
        adapter.status = function () {
            return displayPlacementCount() ? { text: plural(adapter.dirtyCount(), 'unsaved change'), tone: 'warn' } : status.call(adapter);
        };
        adapter.actions = function () {
            var result = actions.call(adapter);
            if (displayPlacementCount()) {
                result.primary = { label: 'Publish', icon: 'cloud-upload', disabled: false, title: 'Put your changes on the live website' };
                if (result.secondary) result.secondary = Object.assign({}, result.secondary, { disabled: true });
            }
            return result;
        };
        adapter.runPrimary = function () {
            if (displayPlacementCount() && dirty.call(adapter) === 0) {
                confirmPublish('Publish the updated placements to the live website?', function () {
                    run('Publishing…', function () { toast('Published. Your changes are live now.', 'ok'); });
                });
            } else if (primary) return primary.call(adapter);
        };
    }
    withImagePositions(S.adapter);
    if (window.GNCEditorDrafts) drafts=window.GNCEditorDrafts.create({
        page:C.page.slug,adapter:function(){return S.adapter;},document:function(){return S.doc;},
        pending:function(){return S.adapter.dirtyCount();},positions:function(){return imagePositions;},
        textPlacements:function(){return textPlacements;},setTextPlacements:function(value){textPlacements=value;applyTextPlacements();},
        setPositions:function(value){imagePositions=value;Object.keys(value).forEach(function(path){previewImagePosition(path,value[path].value);});},
        clean:cleanHtml,notify:toast,refresh:chrome,
        send:function(fields){if(C.reviewSubmission)fields.review_submission=C.reviewSubmission;return send('/admin/action/editor-draft-handler.php',fields);}
    });
    function savePageDraft(){if(drafts && drafts.supported() && !S.busy)return run('Saving draft…',function(){return drafts.save();});}
    var draftSaveButton=$('#veSaveDraft'),draftDiscardButton=$('#veDiscardDraft');
    if(draftSaveButton)draftSaveButton.addEventListener('click',savePageDraft);
    if(draftDiscardButton)draftDiscardButton.addEventListener('click',async function(){
        if(S.busy||!await window.AdminDialogs.confirm('Discard the saved draft and return to the published page?'))return;
        await run('Discarding draft…',async function(){await drafts.clear();leaving=true;window.location.reload();});
    });
    S.url = C.page.url;
    if (kind === 'organizations') window.OrganizationEditor = { hasChanges: unsavedPageChanges };

    var ob = $('#veOutlineBtn');
    if (ob) ob.addEventListener('click', function () { var r = ob.getBoundingClientRect(); if (S.adapter.openOutline) S.adapter.openOutline(r.left, r.bottom + 8); });
    var sb = $('#veSettingsBtn'); if (sb) sb.addEventListener('click', openDrawer);
    if (btnPageOpts) btnPageOpts.addEventListener('click', function () {
        var r = btnPageOpts.getBoundingClientRect();
        if (S.adapter.pageOptions) S.adapter.pageOptions(Math.max(8, r.left - 140), r.bottom + 8);
    });
    if (btnPosts) btnPosts.addEventListener('click', function () { var r = btnPosts.getBoundingClientRect(); postsMenu(Math.max(8, r.left - 140), r.bottom + 8); });
    if (C.embed && C.embed.open) {            // arrived from the list (or a link) to edit a specific post: open it straight away
        window.setTimeout(function () {
            var file = String(C.embed.open).split('?')[0], mods = C.embed.modules || [], label = 'News, announcements and events';
            mods.forEach(function (m) { if (String(m.list).split('?')[0] === file) label = m.label; });
            openEmbed(C.embed.open, label);
        }, 250);
    }
    var sub = $('#veSub');
    if (sub) sub.addEventListener('change', async function () {
        if (!await guardDirty()) {
            Array.prototype.forEach.call(sub.options, function (option) {
                if (same(entityRoute(option.value), entityRoute(S.url))) sub.value = option.value;
            });
            return;
        }
        loadFrame(sub.value);
    });
    var addFirst = $('#veAddFirst'); if (addFirst) addFirst.addEventListener('click', function () { var d = $('#veDock .ve-add-slide'); if (d) d.click(); });
    window.addEventListener('resize', function () { positionStatus(); if (S.sel) placeTb(true); });

    // Bootstrap modals opened from inside the side panel must sit above the editor, so lift them to <body>.
    D.addEventListener('show.bs.modal', function (e) { var m = e.target; if (m && m.parentNode !== D.body) D.body.appendChild(m); });

    window.VE = { state: S, adapter: function () { return S.adapter; }, cleanHtml: cleanHtml };   // handy for support and tests
    setStatus('Opening the page…', 'busy');
    if (!S.url && S.adapter.startFallback) S.adapter.startFallback(); else loadFrame(S.url);
})();
