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
    function countWords(html) {
        var t = (html || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').trim();
        return t ? t.split(/\s+/).length : 0;
    }
    window.__ceCleanHtml = cleanHtml; // exposed for tests

    // ══════════════════ notifications ══════════════════
    var toastBox = $('#ceToasts');
    function toast(message, type) {
        if (!toastBox) return;
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
        Object.keys(fields || {}).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]); });
        Object.keys(files || {}).forEach(function (k) { if (files[k]) fd.append(k, files[k], files[k].name); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (res) {
            return res.json().catch(function () {
                return { error: 'The server sent an unexpected reply (code ' + res.status + '). Please reload the page and try again.' };
            }).then(function (j) {
                j.ok = res.ok && j.success !== false && !j.error;
                if (!j.ok && !j.error) j.error = 'Something went wrong. Please try again.';
                return j;
            });
        }, function () {
            return { ok: false, error: 'We could not reach the server. Please check your internet connection and try again.' };
        });
    }
    function sendContent(fields, files) { return send(C.contentHandler, fields, files); }
    function sendReview(fields) { return send(C.reviewHandler, fields); }

    // ══════════════════ editor state ══════════════════
    var S = {
        frame: $('#veFrame'), stage: $('#veStage'), doc: null, win: null,
        sel: null, adapter: null, device: 'desktop', fallback: false,
        busy: false, scrollKeep: null, loadCount: 0, hoverEl: null
    };
    var tb = $('#veTb'), pop = $('#vePop'), statusEl = $('#veStatus'), loader = $('#veLoader');
    var btnSecondary = $('#veSecondary'), btnPrimary = $('#vePrimary');
    var fileInput = $('#veFile');

    // ══════════════════ file picker ══════════════════
    function pickFile(accept, cb, personPhoto) {
        fileInput.value = '';
        if (personPhoto) fileInput.setAttribute('data-photo-check', '');
        else fileInput.removeAttribute('data-photo-check');
        fileInput.setAttribute('accept', accept || 'image/*');
        fileInput.onchange = function () { var f = fileInput.files && fileInput.files[0]; if (f) cb(f); };
        fileInput.click();
    }
    function pickPersonPhoto(cb) {
        pickFile('image/jpeg,image/png,image/webp', cb, true);
    }
    function previewUrl(file) { return URL.createObjectURL(file); }

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
        popBelowToolbar(h('div', { class: 've-pop-form' }, [h('label', { class: 've-pop-label', text: opts.label }), input, opts.hint ? h('small', { class: 've-pop-hint', text: opts.hint }) : null, row]));
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
        '.gnc-backtop,#gncBackTop,.gnc-scroll-progress,#gnc-intro,.chatbot,[id*="chatbot"]{display:none!important}',
        '.ve-hover{outline:2px dashed rgba(211,166,58,.95)!important;outline-offset:4px!important}',
        '.ve-selected{outline:2px solid #1f7a3d!important;outline-offset:4px!important}',
        '[contenteditable="true"],[contenteditable="plaintext-only"]{cursor:text;caret-color:#1f7a3d}',
        '[data-ve-field]:focus,[data-ve-section]:focus{outline:2px solid #1f7a3d!important;outline-offset:4px!important}',
        '.ve-empty::before{content:attr(data-ve-ph);opacity:.55;font-style:italic;pointer-events:none}',
        '[data-ve-field="home-text"][data-ve-multiline]{white-space:pre-line}',
        '[data-ve-field][data-ve-multiline]{white-space:pre-wrap}',
        '[data-ve-item],[data-ve-pick],[data-ve-post],[data-ve-goto]{cursor:pointer}',
        '[data-ve-field]{cursor:text}',
        '.ve-hidden-card{opacity:.55}',
        '.ve-hidden-badge{display:inline-block;background:#b45309;color:#fff;font:700 10px/1 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:4px 8px;border-radius:999px;margin-bottom:8px}',
        '.ve-previewing .ve-add-tile,.ve-previewing .ve-hero-hint,.ve-previewing .ve-hidden-badge{display:none!important}',
        '.ve-previewing .ve-hover,.ve-previewing .ve-selected{outline:none!important}',
        '.ve-previewing [data-ve-field],.ve-previewing [data-ve-item],.ve-previewing [data-ve-pick],.ve-previewing [data-ve-post],.ve-previewing [data-ve-goto],.ve-previewing [data-ve-img]{cursor:default!important}',
        '[data-ve-img],[data-ve-person],[data-ve-office],[data-ve-slide],[data-ve-icon]{cursor:pointer}',
        '[data-ve-img]{-webkit-user-drag:none;user-select:none}',
        '.ve-ghost{opacity:.65!important;background:transparent!important;border:2px dashed currentColor!important;box-shadow:none!important}',
        '.ve-ghost svg{display:none}',
        '[data-ve-status="draft"]::after{content:"DRAFT \\00B7  hidden from visitors";position:absolute;top:92px;right:20px;z-index:30;background:#b45309;color:#fff;font:700 11px/1 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;padding:8px 12px;border-radius:99px;pointer-events:none}',
        '.ve-locked{opacity:.85;cursor:not-allowed}',
        '.ve-add-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:220px;border:2px dashed #1f7a3d;border-radius:14px;color:#1f7a3d;background:rgba(31,122,61,.05);font:600 15px system-ui,sans-serif;cursor:pointer;text-align:center;padding:1rem}',
        '.ve-add-tile:hover{background:rgba(31,122,61,.12)}',
        '.ve-extra{max-width:900px;margin:0 auto;padding:2rem 1.25rem 4rem}',
        '.ve-extra>h3{font:700 13px system-ui,sans-serif;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0 0 1rem}',
        '.ve-flash{animation:veFlash 1.2s ease}@keyframes veFlash{0%,60%{box-shadow:0 0 0 6px rgba(211,166,58,.55)}100%{box-shadow:0 0 0 0 rgba(211,166,58,0)}}'
    ].join('\n');

    function injectStyle(doc) {
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
        S.doc = doc; S.win = S.frame.contentWindow; S.loadCount++;
        injectStyle(doc); bindDoc(doc);
        var again = S.adapter.mount(doc);
        if (again) return;                       // adapter swapped in its own canvas; wait for that load
        loader.hidden = true;
        if (S.preview) applyPreview();
        if (S.scrollKeep !== null) { S.win.scrollTo(0, S.scrollKeep); S.scrollKeep = null; }
        chrome();
        if (S.adapter.afterMount) S.adapter.afterMount();
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
        if (v.blocks) return blocksText(el);
        var t = String(el.innerText != null ? el.innerText : el.textContent).replace(/\u00a0/g, ' ').replace(/\r/g, '');
        t = v.multi ? t.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim() : t.replace(/\s+/g, ' ').trim();
        return t;
    }
    function markEmpty(el) { el.classList.toggle('ve-empty', readField(el) === ''); }
    /** Make an element on the page a plain-text field. onChange(el, text) runs on every edit. */
    function makeField(el, onChange, locked) {
        var ro = !!locked;
        el.setAttribute('contenteditable', ro ? 'false' : (el.hasAttribute('data-ve-blocks') ? 'true' : PLAIN));
        el.setAttribute('spellcheck', 'true');
        el.__ve = {
            field: true, max: parseInt(el.getAttribute('data-ve-max') || '0', 10) || 0,
            multi: el.hasAttribute('data-ve-multiline'), blocks: el.hasAttribute('data-ve-blocks'),
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
    var HOVER_SEL = '[data-ve-field],[data-ve-section],[data-ve-img],[data-ve-basic-image],[data-ve-basic-logo-id],[data-ve-person],[data-ve-office],[data-ve-add],[data-ve-icon],[data-ve-item],[data-ve-pick],[data-ve-post],[data-ve-goto]';
    function bindDoc(doc) {
        var win = doc.defaultView;
        doc.addEventListener('click', function (e) {
            var t = e.target;
            if (S.preview) {                      // Preview: nothing is editable; links still move between pages
                var pa = t.closest ? t.closest('a') : null;
                if (pa) { e.preventDefault(); if (S.adapter.onLink) S.adapter.onLink(pa); }
                return;
            }
            var d = S.adapter.pick(t, e);
            var a = t.closest ? t.closest('a') : null;
            if (d && d.kind === 'none') { e.preventDefault(); return; }       // the item already opened its own popover
            if (d) {
                if (d.prevent !== false && !(d.el && d.el.isContentEditable && !d.noCaret)) e.preventDefault();
                else if (a) e.preventDefault();
                select(d); return;
            }
            if (a) {
                e.preventDefault();
                var nav = S.adapter.onLink ? S.adapter.onLink(a) : false;
                if (!nav) toast('Links are switched off while you edit. Use the page list at the top to move between pages.', 'ok');
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
            if (!S.sel || S.sel.kind !== 'rich' || raf) return;
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
            top = fr.top + r.top - th - 10;
            if (top < fr.top + 6) top = Math.min(fr.top + Math.max(r.bottom, 0) + 10, fr.bottom - th - 8);
            if (r.height > fr.height - th - 40 && r.top < th + 16) top = fr.top + 10;
            left = fr.left + r.left;
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
        if (el && el.__ve && el.__ve.onChange) el.__ve.onChange(el);
        renderToolbar(); chrome();
    }

    // ══════════════════ top bar: status, Save draft / Publish, devices ══════════════════
    function setStatus(text, tone) {
        statusEl.className = 've-status ve-status-' + (tone || 'ok');
        statusEl.textContent = '';
        statusEl.appendChild(icon(tone === 'warn' ? 'circle-fill' : tone === 'busy' ? 'arrow-repeat' : tone === 'err' ? 'exclamation-circle-fill' : 'check-circle-fill'));
        statusEl.appendChild(D.createTextNode(' ' + text));
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
        if (!S.busy) { var st = S.adapter.status(); setStatus(st.text, st.tone); }
        var o = $('#veOutlineBtn'); if (o) o.hidden = !S.adapter.outline;
        if (btnCancel) btnCancel.hidden = !(S.adapter.dirtyCount() > 0) || S.preview;
        if (btnPageOpts) btnPageOpts.hidden = !(S.adapter.hasPageOptions && S.adapter.hasPageOptions()) || S.preview;
    }
    /** Run an async save with the buttons locked and a status message. */
    function run(label, fn) {
        if (S.busy) return Promise.resolve();
        S.busy = true; setStatus(label, 'busy'); chrome();
        return Promise.resolve().then(fn).catch(function (e) {
            console.error(e); toast('Something went wrong. Please try again.', 'error');
        }).then(function () { S.busy = false; chrome(); });
    }
    function shortcutSave() {
        var a = S.adapter.actions(); var b = a.secondary && !a.secondary.disabled ? btnSecondary : btnPrimary;
        if (b && !b.disabled && !b.hidden) b.click();
    }
    D.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); shortcutSave(); } });
    btnSecondary.addEventListener('click', function () { if (S.adapter.runSecondary) S.adapter.runSecondary(); });
    btnPrimary.addEventListener('click', function () { if (S.adapter.runPrimary) S.adapter.runPrimary(); });

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
        if (!leaving && S.adapter && S.adapter.dirtyCount() > 0) { e.preventDefault(); e.returnValue = ''; }
    });
    D.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-ve-nav]') : null;
        if (!a) return;
        if (S.adapter && S.adapter.dirtyCount() > 0 && !window.confirm('You have unsaved changes on this page.\n\nLeave without saving them?')) { e.preventDefault(); return; }
        leaving = true;
    });
    function guardDirty(msg) {
        return !(S.adapter && S.adapter.dirtyCount() > 0) || window.confirm(msg || 'This reloads the preview, so your unsaved changes would be lost.\n\nContinue?');
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
        if ((m = /\/basic-edu-department\/([a-z0-9-]+)\.php$/i.exec(p))) return { page: 'basic-education', sub: m[1] };
        return null;
    }
    /** Reload the editor screen, optionally on another page: ({page, sub, program}). */
    function reloadEditor(msg, route) {
        if (msg) toastNextLoad(msg, 'ok');
        leaving = true;
        if (!route) { window.location.reload(); return; }
        var q = '?page=' + encodeURIComponent(route.page) + (route.sub ? '&sub=' + encodeURIComponent(route.sub) : '') + (route.program ? '&program_id=' + encodeURIComponent(route.program) : '');
        window.location.href = window.location.pathname + q;
    }
    /** Cancel: throw away what is not saved yet by loading everything fresh from the server. */
    if (btnCancel) btnCancel.addEventListener('click', function () {
        var n = S.adapter ? S.adapter.dirtyCount() : 0;
        if (!n) return;
        if (!window.confirm('Throw away ' + plural(n, 'unsaved change') + '?\n\nThe page goes back to what is saved now.')) return;
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
        function paintPhoto(el, change) {
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
                        if (el) {
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
                        toast('History photo change published.', 'ok');
                    });
                });
            }, Promise.resolve()).then(function () { renderToolbar(); });
        }
        var secs = (C.sections || []).map(function (s) {
            return { key: s.key, title: s.title, html: s.html || '', has_draft: !!s.has_draft, review: s.review_status || 'none',
                     note: s.review_note || '', by: s.submitted_by || '', el: null, base: '', dirty: false };
        });
        function locked(s) { return s.review === 'pending'; }
        function byEl(el) { for (var i = 0; i < secs.length; i++) if (secs[i].el === el) return secs[i]; return null; }
        function html(s) { return cleanHtml(s.el.innerHTML); }

        function bind(s) {
            var el = s.el;
            el.__ve = { rich: true, sec: s, onChange: function () { s.dirty = html(s) !== s.base; } };
            el.innerHTML = s.html || '<p></p>';
            el.setAttribute('data-ve-ph', 'Write here…');
            el.setAttribute('spellcheck', 'true');
            setLock(s);
            el.classList.toggle('ve-empty', !(el.textContent || '').trim());
            s.base = html(s); s.dirty = false;
        }
        function setLock(s) {
            if (!s.el) return;
            s.el.setAttribute('contenteditable', locked(s) ? 'false' : 'true');
            s.el.classList.toggle('ve-locked', locked(s));
            s.el.title = locked(s) ? 'Waiting for approval, so it can’t be changed right now.' : '';
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

        function chipFor(s) {
            if (locked(s)) return { type: 'chip', tone: 'warn', icon: 'hourglass-split', text: 'Waiting for approval' };
            if (s.review === 'rejected') return { type: 'chip', tone: 'err', icon: 'arrow-counterclockwise', text: 'Changes requested', title: s.note || '' };
            if (s.dirty) return { type: 'chip', tone: 'warn', icon: 'pencil', text: 'Unsaved' };
            if (s.has_draft) return { type: 'chip', tone: 'warn', icon: 'file-earmark-text', text: 'Draft · not live' };
            return { type: 'chip', tone: 'ok', icon: 'broadcast', text: 'Live' };
        }

        function apply(s, res, replace) {
            s.has_draft = !!res.has_draft; s.review = res.review_status || 'none'; s.note = res.review_note || ''; s.by = res.submitted_by_name || '';
            if (replace && res.editor_html !== undefined) { s.html = res.editor_html; s.el.innerHTML = res.editor_html || '<p></p>'; s.el.classList.toggle('ve-empty', !(s.el.textContent || '').trim()); }
            s.base = html(s); s.dirty = false; setLock(s);
        }
        function each(list, action) {
            var ok = 0, errs = [];
            return list.reduce(function (p, s) {
                return p.then(function () {
                    return sendReview({ action: action, page: page, section_key: s.key, content: html(s) }).then(function (res) {
                        if (res.ok) { ok++; apply(s, res, action === 'discard'); } else errs.push(s.title + ': ' + res.error);
                    });
                });
            }, Promise.resolve()).then(function () { return { ok: ok, errs: errs }; });
        }
        function report(r, doneWord) {
            if (r.ok) toast(plural(r.ok, 'section') + ' ' + doneWord + '.', 'ok');
            r.errs.forEach(function (m) { toast(m, 'error'); });
        }
        function publishable(s) { return !!s.el && (s.dirty || s.has_draft || locked(s)); }
        function submittable(s) { return !!s.el && !locked(s) && (s.dirty || (s.has_draft && s.review !== 'pending')); }
        function publishSections(list) {
            run('Publishing…', function () { return each(list, 'publish').then(function (r) { report(r, 'published. It is live now'); return publishPhotos(); }); });
        }

        function review(s, what) {
            if (what === 'approve') {
                if (!window.confirm('Approve and publish this change?\n\nIt will go live on the website.')) return;
                return run('Publishing…', function () { return sendReview({ action: 'approve', page: page, section_key: s.key }).then(function (res) {
                    if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
            }
            askText({ label: 'Note to the editor (what should change?)', required: true, multiline: true, max: 500, cta: 'Send back', done: function (note) {
                run('Sending back…', function () { return sendReview({ action: 'reject', page: page, section_key: s.key, note: note }).then(function (res) {
                    if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
            } });
        }

        var A = {
            outline: true,
            startFallback: function () { loadSrcdoc(fallbackHtml()); },
            mount: function (doc) {
                var found = 0;
                secs.forEach(function (s) { s.el = doc.querySelector('[data-ve-section="' + cssEsc(s.key) + '"]'); if (s.el) found++; });
                if (!found && !S.fallback) { loadSrcdoc(fallbackHtml()); return true; }
                addExtras(doc);
                secs.forEach(bind);
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
            pick: function (t) {
                var photo = t && t.closest ? t.closest('[data-ve-img^="history:"]') : null;
                if (photo) return { kind: 'history-image', el: photo, key: photo.getAttribute('data-ve-img').slice(8), prevent: true };
                var el = sectionElOf(t); var s = el && byEl(el);
                return s ? { kind: 'rich', el: el, sec: s, prevent: false, noCaret: locked(s) } : null;
            },
            toolbar: function (sel) {
                var s = sel.sec, it = [];
                if (sel.kind === 'history-image') {
                    var key = sel.key, hasImage = sel.el.getAttribute('data-ve-has-image') === '1';
                    it.push({ type: 'chip', icon: 'image', text: photos[key] ? 'Unsaved photo change' : (hasImage ? 'History photo' : 'Empty photo slot') });
                    it.push({ icon: 'image', label: hasImage ? 'Replace photo' : 'Add photo', primary: true, run: function () {
                        pickFile('image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp', function (file) {
                            if (S.busy) return;
                            if (file.size > 10 * MB) { toast('That photo is over 10 MB.', 'error'); return; }
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
                if (!locked(s)) {
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
                }
                it.push(chipFor(s));
                if (locked(s) && C.canApprove) {
                    it.push({ icon: 'check2-circle', label: 'Approve & publish', primary: true, run: function () { review(s, 'approve'); } });
                    it.push({ icon: 'arrow-counterclockwise', label: 'Request changes', run: function () { review(s, 'reject'); } });
                } else if (locked(s)) {
                    it.push({ icon: 'arrow-90deg-left', label: 'Take back', title: 'Take this back so you can keep editing', run: function () {
                        run('Taking back…', function () { return sendReview({ action: 'withdraw', page: page, section_key: s.key }).then(function (res) { if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); }); } });
                } else if (s.dirty || s.has_draft) {
                    it.push({ icon: 'arrow-counterclockwise', title: s.has_draft ? 'Throw away my draft and go back to what is live' : 'Undo my unsaved changes', run: function () {
                        if (!window.confirm(s.has_draft ? 'Discard your draft and go back to what is on the website?' : 'Undo your unsaved changes in this section?')) return;
                        if (!s.has_draft) { s.el.innerHTML = s.base || '<p></p>'; s.dirty = false; renderToolbar(); chrome(); return; }
                        run('Discarding…', function () { return sendReview({ action: 'discard', page: page, section_key: s.key }).then(function (res) { if (res.ok) { apply(s, res, true); toast(res.message, 'ok'); } else toast(res.error, 'error'); }); });
                    } });
                }
                return it;
            },
            dirtyCount: function () { return secs.filter(function (s) { return s.dirty; }).length + Object.keys(photos).length; },
            status: function () {
                var n = A.dirtyCount(); if (n) return { text: plural(n, 'change') + ' not saved', tone: 'warn' };
                var p = secs.filter(locked).length; if (p) return { text: plural(p, 'section') + ' waiting for approval', tone: 'warn' };
                var d = secs.filter(function (s) { return s.has_draft; }).length; if (d) return { text: plural(d, 'draft') + ' saved · not live yet', tone: 'warn' };
                return { text: 'Everything here is live', tone: 'ok' };
            },
            actions: function () {
                var n = A.dirtyCount();
                var secondary = { label: 'Save draft', icon: 'save', disabled: n === 0 || Object.keys(photos).length > 0, title: Object.keys(photos).length ? 'Photo changes stay in this preview until Publish. Draft saving is available for text only.' : 'Keep your changes privately. Visitors cannot see them yet.' };
                if (C.canPublish) return { secondary: secondary, primary: { label: 'Publish', icon: 'cloud-upload', disabled: !secs.some(publishable) && !Object.keys(photos).length, title: 'Put your changes on the live website' } };
                return { secondary: secondary, primary: { label: 'Submit for approval', icon: 'send', disabled: !C.reviewReady || !secs.some(submittable), title: C.reviewReady ? 'Send your changes to an administrator' : 'Approval has not been switched on yet' } };
            },
            runSecondary: function () {
                if (Object.keys(photos).length) return;
                var list = secs.filter(function (s) { return s.dirty && !locked(s); }); if (!list.length) return;
                run('Saving draft…', function () { return each(list, 'save_draft').then(function (r) { report(r, 'saved as a draft (visitors cannot see it yet)'); }); });
            },
            runPrimary: function () {
                if (C.canPublish) {
                    var list = secs.filter(publishable), count = list.length + Object.keys(photos).length; if (!count) return;
                    confirmPop('Publish these changes?', plural(count, 'change') + ' will go live on the website.', 'Publish', function () { publishSections(list); }, false);
                    return;
                    run('Publishing…', function () { return each(list, 'publish').then(function (r) { report(r, 'published. It is live now'); }); });
                } else {
                    var l2 = secs.filter(submittable); if (!l2.length) return;
                    run('Sending…', function () { return each(l2, 'submit').then(function (r) { report(r, 'sent for approval'); }); });
                }
            },
            openOutline: function (x, y) {
                var box = h('div', { class: 've-outline' }, [h('div', { class: 've-pop-label', text: 'Sections on this page' })].concat(secs.map(function (s) {
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
        function addSlide() {
            if (!guardDirty('Adding a slide reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
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
        function deleteSlide(s) {
            if (!guardDirty('Deleting a slide reloads the preview, so your other unsaved changes would be lost.\n\nContinue?')) return;
            if (!window.confirm('Delete this slide for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
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
                    it.push({ icon: 'image', label: 'Replace picture', primary: true, run: function () {
                        pickFile('image/*', function (f) { hcFiles[hk] = f; delete hcReset[hk]; sel.el.style.backgroundImage = 'url("' + previewUrl(f) + '")'; sel.el.classList.remove('lazy-bg'); renderToolbar(); chrome(); }); } });
                    if (changed || (HC[hk] && HC[hk].value !== HC[hk].default && !hcReset[hk])) it.push({ icon: 'arrow-counterclockwise', label: 'Use original', title: 'Go back to the original picture', run: function () {
                        delete hcFiles[hk]; hcReset[hk] = true; sel.el.style.backgroundImage = 'url("' + HC[hk].default + '")'; renderToolbar(); chrome(); } });
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
                if (!window.confirm('Publish your changes to the live website?' + (published ? '\n\n' + plural(published, 'published slide') + ' will change for visitors.' : ''))) return;
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
                        if (done) toast('Saved. ' + (published ? 'Published changes are live now.' : 'Draft slides stay hidden from visitors.'), 'ok');
                        if (done && !errs.length) return refresh(cur);       // reload from the server: proves what was saved
                        if (done) return refresh(cur);
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
            var go = h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: o ? 'Save office' : 'Add office', on: { click: function () {
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
            if (res.sections) secs = res.sections; toast(msg || res.message || 'Saved.', 'ok'); return true;
        }
        function reload() { loadFrame(S.url, true); }

        // ── structural actions (add / archive / delete): done on purpose, so they apply straight away ──
        function addPerson(key, pos, anchorX, anchorY) {
            if (!guardDirty('Adding a person reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            openPop(personForm(function (v, photo) {
                run('Adding…', function () { return sendContent({ action: 'admin_save_person', person_id: 0, section_key: key, name: v.name, role: v.role }, { photo: photo }).then(function (r) { if (take(r, 'Added. They are on the page now.')) reload(); }); });
            }), anchorX, anchorY);
        }
        function addOffice(key, anchorX, anchorY) {
            if (!guardDirty('Adding an office reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            openPop(officeForm(secBy(key), null, function (v, photo) {
                run('Adding…', function () { v.action = 'admin_save_office'; v.office_id = 0; v.section_key = key; return sendContent(v, { photo: photo }).then(function (r) { if (take(r, 'Added. The office is on the page now.')) reload(); }); });
            }), anchorX, anchorY, null, true);
        }
        function archiveItem(kind, id, name) {
            if (!guardDirty('This reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            if (!window.confirm('Hide “' + name + '” from the website?\n\nThey are kept safely. You can bring them back from “More settings” → Archived.')) return;
            run('Archiving…', function () { var f = { action: kind === 'office' ? 'admin_archive_office' : 'admin_archive_person', archived: '1' }; f[kind === 'office' ? 'office_id' : 'person_id'] = id;
                return sendContent(f).then(function (r) { if (take(r, 'Hidden from the website.')) reload(); }); });
        }
        function deleteItem(kind, id, name) {
            if (!guardDirty('This reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return;
            if (!window.confirm('Delete “' + name + '” for good?\n\nThis removes them from the website now and cannot be undone. (Use Archive to keep them.)')) return;
            run('Deleting…', function () { var f = { action: kind === 'office' ? 'admin_delete_office' : 'admin_delete_person' }; f[kind === 'office' ? 'office_id' : 'person_id'] = id;
                return sendContent(f).then(function (r) { if (take(r, 'Deleted.')) reload(); }); });
        }
        function shift(sel, dir) {
            var u = unitOf(sel.el), g = u.parentNode, sibs = Array.prototype.filter.call(g.children, function (c) { return c.getAttribute('data-ve-add') === null; });
            var i = sibs.indexOf(u), j = i + dir; if (j < 0 || j >= sibs.length) return;
            if (dir < 0) g.insertBefore(u, sibs[j]); else g.insertBefore(u, sibs[j].nextSibling);
            var key = g.getAttribute('data-ve-key') || curKey;
            edits.order[key] = idsInGrid(g, sel.kind === 'office' ? 'data-ve-office' : 'data-ve-person');
            renderToolbar(); chrome();
        }
        function photoOf(sel) {
            return { get: function () { return sel.kind === 'office' ? edits.office[sel.id] : edits.person[sel.id]; },
                     map: sel.kind === 'office' ? edits.office : edits.person };
        }
        function replacePhoto(sel) {
            pickPersonPhoto(function (f) {
                var m = photoOf(sel).map; var e = m[sel.id] || (m[sel.id] = {}); e.file = f; e.remove = false;
                var img = sel.el.querySelector('img'); if (img) { img.src = previewUrl(f); img.style.opacity = ''; }
                renderToolbar(); chrome();
            });
        }
        function removePhoto(sel) {
            var m = photoOf(sel).map; var e = m[sel.id] || (m[sel.id] = {}); e.file = null; e.remove = true;
            var img = sel.el.querySelector('img'); if (img) img.style.opacity = '.2';
            renderToolbar(); chrome();
        }
        function editOffice(sel) {
            var r = officeBy(sel.id); if (!r) return;
            var tbr = tb.getBoundingClientRect();
            openPop(officeForm(r.sec, r.o, function (v, photo) {
                if (!guardDirty('Saving an office reloads the preview, so your other unsaved changes would be lost.\n\nContinue?')) return;
                run('Saving…', function () { v.action = 'admin_save_office'; v.office_id = sel.id; v.section_key = r.sec.key; return sendContent(v, { photo: photo }).then(function (res) { if (take(res, 'Office saved.')) reload(); }); });
            }), tbr.left, tbr.bottom + 8, null, true);
        }

        var A = {
            mount: function (doc) { bind(doc); return false; },
            onLink: function (a) {
                var href = a.getAttribute('href') || '', m = /[?&]section=([^&#]+)/.exec(href);
                if (!m) return false;
                if (!guardDirty('Switching tabs reloads the preview, so your unsaved changes would be lost.\n\nContinue?')) return true;
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
            status: function () { var n = A.dirtyCount(); return n ? { text: plural(n, 'unsaved change'), tone: 'warn' } : { text: 'Everything here is live', tone: 'ok' }; },
            actions: function () { return { secondary: null, primary: { label: 'Publish', icon: 'cloud-upload', disabled: A.dirtyCount() === 0, title: 'Put your changes on the live website' } }; },
            runPrimary: function () {
                if (!A.dirtyCount()) return;
                for (var id in edits.person) { var e0 = edits.person[id]; if (e0.name != null && !e0.name.trim()) { toast('A person’s name cannot be empty.', 'error'); return; } }
                for (var k in edits.sec) { if (edits.sec[k].title != null && !edits.sec[k].title.trim()) { toast('A heading cannot be empty.', 'error'); return; } }
                if (!window.confirm('Publish your changes to the live website?')) return;
                run('Publishing…', function () {
                    var p = Promise.resolve(), errs = [], done = 0;
                    function step(fields, files) { p = p.then(function () { return sendContent(fields, files).then(function (r) { if (r.ok) { done++; if (r.sections) secs = r.sections; } else errs.push(r.error); }); }); }
                    Object.keys(edits.sec).forEach(function (k) { var s = secBy(k), e = edits.sec[k]; step({ action: 'admin_save_section', section_key: k, title: e.title != null ? e.title : s.title, description: e.desc != null ? e.desc : s.desc }); });
                    Object.keys(edits.person).forEach(function (id) {
                        var r = personBy(+id), e = edits.person[id]; if (!r) return;
                        step({ action: 'admin_save_person', person_id: id, section_key: r.sec.key, name: e.name != null ? e.name : r.p.name, role: e.role != null ? e.role : r.p.role, remove_photo: e.remove ? '1' : '' }, { photo: e.file });
                    });
                    Object.keys(edits.office).forEach(function (id) {
                        var r = officeBy(+id), e = edits.office[id]; if (!r) return; var o = r.o, c = o.contact || {};
                        step({ action: 'admin_save_office', office_id: id, section_key: r.sec.key, office_name: o.office, head_name: o.name, head_role: o.role, phone: c.phone, email: c.email, location: c.location, hours: c.hours, services: (o.services || []).join('\n'), remove_photo: e.remove ? '1' : '' }, { photo: e.file });
                    });
                    Object.keys(edits.order).forEach(function (k) { step({ action: 'admin_reorder', section_key: k, order: JSON.stringify(edits.order[k]) }); });
                    return p.then(function () {
                        errs.forEach(function (m) { toast(m, 'error'); });
                        if (done) { toast('Published. Your changes are live now.', 'ok'); }
                        if (!errs.length) edits = { sec: {}, person: {}, office: {}, order: {} };
                        if (done) reload();
                    });
                });
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: Departments and Programs (real page + the existing manager on demand) ══════════════════
    function EntityAdapter() {
        // ── Programs: slide strips under the page (top banner + Facilities), like the homepage slides ──
        var dock = $('#veDock');
        var PROG_URL = '/admin/action/program-page-handler.php';   // same endpoint as the tabs in "More settings"
        var LISTS = {
            banner: {
                label: 'Banner slides', icon: 'collection-play', itemType: 'hero_slide', data: null, sequential: true,
                accept: 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm'
            },
            facility: {
                label: 'Facility slides', icon: 'images', itemType: 'facility', data: null, sequential: false,
                accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'
            }
        };
        var tab = 'banner';   // which strip is showing (kept while the preview reloads)

        function readData(doc, id) {
            try { var el = doc.getElementById(id); return el ? JSON.parse(el.textContent) : null; } catch (e) { return null; }
        }
        function nameFromFile(n) { n = String(n).replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim(); return n ? n.charAt(0).toUpperCase() + n.slice(1, 120) : 'Slide'; }
        function pickFiles(accept, cb) {
            fileInput.value = ''; fileInput.setAttribute('accept', accept); fileInput.multiple = true;
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
            if (isImg && f.size > 10 * MB) return 'over 10 MB';
            return '';
        }
        /** The Facilities tab in "More settings" keeps its own copy of that list; warn before replacing unsaved edits there. */
        function guardDrawer(L) {
            if (L.itemType !== 'facility') return true;
            var d = D.querySelector('.pgm-card[data-pid="' + L.data.program_id + '"] .pgm-panel[data-panel="facility"] .pgm-dirty');
            return !(d && !d.hidden) || window.confirm('The Facilities tab in “More settings” has changes you have not saved. Continuing will discard them.\n\nContinue?');
        }
        function itemToRow(i) { return { item_id: i.item_id, title: i.title, body: i.body || '', file: null }; }
        function rowsOf(list) { return list.map(itemToRow); }

        /** Send the whole list (the server keeps rows by item_id, adds item_id 0, and removes the ones left out). */
        function postRows(L, rows) {
            var fields = {
                action: 'save_program_items', program_id: L.data.program_id, item_type: L.itemType,
                items: JSON.stringify(rows.map(function (r) { return { item_id: r.item_id || 0, title: r.title, subtitle: '', body: r.body || '', icon: '', letter: '', remove_image: false }; }))
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

        function addSlides() {
            var L = LISTS[tab]; if (!L || !L.data) return;
            if (L.data.slides.length >= L.data.limit) { toast('This slideshow is full (' + L.data.limit + ' slides). Delete one to add another.', 'error'); return; }
            if (!guardDrawer(L)) return;
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
        function moveSlide(from, to) {
            var L = LISTS[tab]; if (!L || !L.data) return;
            var n = L.data.slides.length;
            if (from === to || from < 0 || to < 0 || from >= n || to >= n) return;
            if (!guardDrawer(L)) return;
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
                    var sec = S.doc.getElementById('facilities'); if (sec) sec.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    var dots = S.doc.querySelectorAll('.fcar-dot'); if (dots[i]) dots[i].click();
                }
            } catch (e) {}
        }
        function openSlide(i, btn) {
            var L = LISTS[tab], s = L && L.data ? L.data.slides[i] : null; if (!s) return;
            showSlide(L, i);
            var needName = L.itemType === 'facility';
            var input = h('input', { class: 'form-control form-control-sm', type: 'text', value: s.title, maxlength: '255' });
            var go = function () {
                var v = input.value.trim();
                if (!v && needName) { input.focus(); return; }
                if (v === s.title) { closePop(); return; }
                if (!guardDrawer(L)) return;
                closePop();
                var rows = rowsOf(L.data.slides); rows[i].title = v;
                commit(L, rows, 'Name saved.');
            };
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
            var del = h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: function () {
                closePop();
                if (!guardDrawer(L)) return;
                if (!window.confirm('Delete “' + (s.title || 'this slide') + '” for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
                var rows = rowsOf(L.data.slides); rows.splice(i, 1);
                commit(L, rows, 'Slide deleted.');
            } } }, [icon('trash'), ' Delete']);
            var r = btn.getBoundingClientRect();
            openPop(h('div', { class: 've-pop-form' }, [
                h('label', { class: 've-pop-label', text: 'Slide ' + (i + 1) + (needName ? ' name' : ' name (optional)') }),
                input,
                h('div', { class: 've-pop-row' }, [h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Save name', on: { click: go } }), del])
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
            var any = LISTS.banner.data || LISTS.facility.data;
            if (!any) { dock.hidden = true; dock.innerHTML = ''; return; }
            if (!LISTS[tab] || !LISTS[tab].data) tab = LISTS.banner.data ? 'banner' : 'facility';
            var L = LISTS[tab];
            dock.hidden = false; dock.innerHTML = '';
            dock.appendChild(h('div', { class: 've-dock-label ve-dock-tabs', role: 'tablist' }, ['banner', 'facility'].filter(function (k) { return LISTS[k].data; }).map(function (k) {
                return h('button', { type: 'button', role: 'tab', 'aria-selected': k === tab ? 'true' : 'false', class: 've-dock-tab' + (k === tab ? ' is-on' : ''),
                    on: { click: function () { if (tab !== k) { closePop(); tab = k; renderStrip(); } } } },
                    [icon(LISTS[k].icon), h('span', { text: LISTS[k].label + ' (' + LISTS[k].data.slides.length + ')' })]);
            })));
            var track = h('div', { class: 've-dock-track', role: 'list' });
            L.data.slides.forEach(function (s, i) {
                var media = s.media_type === 'video' ? h('span', { class: 've-thumb-img ve-thumb-video' }, [icon('camera-video')])
                    : (s.image_url ? h('img', { class: 've-thumb-img', src: s.image_url, alt: '' }) : h('span', { class: 've-thumb-img ve-thumb-video' }, [icon('image')]));
                var b = h('button', { type: 'button', class: 've-thumb', draggable: 'true', role: 'listitem', 'data-i': i,
                    title: 'Click to rename or delete · drag to change the order',
                    on: { click: function () { openSlide(i, b); } } }, [
                    media, h('span', { class: 've-thumb-n', text: String(i + 1) }),
                    h('span', { class: 've-thumb-t', text: clip(s.title || 'Untitled', 22) })]);
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
        var BD = null, bChange = {}, bFiles = {}, bLogos = {};
        var dChange = {}, dFiles = {}, dRemove = {};   // department + head of office: edited texts, new pictures, removed pictures
        var pChange = {}, pMeta = {}, pRows = {};       // program: edited texts, name/colour/visibility, edited cards by list
        function resetPending() { dChange = {}; dFiles = {}; dRemove = {}; pChange = {}; pMeta = {}; pRows = {}; bChange = {}; bFiles = {}; bLogos = {}; }

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
            var n = Object.keys(bChange).length + Object.keys(bFiles).length + Object.keys(bLogos).length;
            if (DD) n += Object.keys(dChange).length + Object.keys(dFiles).length + Object.keys(dRemove).length;
            if (PD) n += Object.keys(pChange).length + Object.keys(pMeta).length + listDirty();
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
            $$('[data-ve-field]', doc).forEach(function (el) {
                var f = el.getAttribute('data-ve-field');
                if (BD && f === 'basic-text') {
                    var bk = el.getAttribute('data-ve-key');
                    if (BD.fields[bk]) makeField(el, function (e, val) {
                        if (val === BD.fields[bk].value) delete bChange[bk]; else bChange[bk] = val;
                    });
                }
                else if (DD && DFIELD[f]) { var k = DFIELD[f]; makeField(el, function (e, val) { dSet(k, val); }); }
                else if (PD && f === 'prog-text') {
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
        function formPop(el, title, fields, cta, done, extra) {
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
                [h('div', { class: 've-pop-row' }, [h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: cta || 'Apply', on: { click: go } })].concat(extra || []))])));
        }
        /** Before an action that reloads the page: warn if other unsaved edits would be lost. */
        function okToReload(except) {
            var others = pendingCount() - (except || 0);
            return others <= 0 || window.confirm('You have other unsaved changes on this page. They will be lost when the page refreshes.\n\nContinue anyway?');
        }
        function routeNow() { return entityRoute(S.url); }
        function syncSub(url) {
            var s = $('#veSub'), want = entityRoute(url || S.url);
            if (!s || !want) return;
            Array.prototype.forEach.call(s.options, function (o) { var r = entityRoute(o.value); if (r && r.page === want.page && r.sub === want.sub) s.value = o.value; });
        }
        function openPage(url) {
            var u; try { u = new URL(url, window.location.origin); } catch (e) { return; }
            if (!guardDirty()) return;
            loadFrame(withParam(u.pathname + u.search, 'ce_preview', '1')); syncSub(u.pathname + u.search);
        }

        // ── departments ──
        function showPic(el, url) {
            var im = el.tagName === 'IMG' ? el : el.querySelector('img');
            el.style.opacity = '';
            if (im) { im.src = url; im.removeAttribute('srcset'); im.style.opacity = ''; }
            else { el.innerHTML = ''; el.style.backgroundImage = 'url("' + url + '")'; el.style.backgroundSize = 'contain'; el.style.backgroundRepeat = 'no-repeat'; el.style.backgroundPosition = 'center'; }
        }
        function editFacebook() {
            var el = S.doc.querySelector('[data-ve-pick="dept-facebook"]'); if (!el || !DD) return;
            var cur = dVal('facebook_url');
            formPop(el, 'Facebook page', [{ name: 'url', label: 'Address of the Facebook page', value: cur, max: 255, placeholder: 'https://www.facebook.com/your-page',
                hint: 'Shows the page’s posts on this department’s page. Leave empty to remove it.' }], 'Apply', function (o) {
                var v = o.url;
                if (v && !/^https?:\/\/(www\.)?facebook\.com\//i.test(v)) { toast('That is not a Facebook page address. It should start with https://www.facebook.com/', 'error'); return; }
                dSet('facebook_url', v); renderToolbar(); chrome();
                toast(v ? 'Link set. Press Publish to show it on the website.' : 'The Facebook box will be removed when you publish.', 'ok');
            });
        }
        function addDepartment(el) {
            formPop(el, 'Add a department', [{ name: 'name', label: 'Department name', max: 255, required: true, placeholder: 'e.g. College of Nursing' }], 'Create', function (o) {
                if (!okToReload()) return;
                run('Creating…', function () {
                    return sendContent({ action: 'save_department_details', dept_id: 0, dept_name: o.name, description: '', hero_text: '', about_text: '', facebook_url: '', office_label: '', theme_color: '' }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Department created. It is live: click its text to fill the page in.', { page: 'college-departments', sub: (r.department && r.department.slug) || '' });
                    });
                });
            });
        }
        function addProgram(el) {
            if (BD || (DD && DD.kind === 'basic')) return;
            var units = Array.isArray(C.programDepartments) ? C.programDepartments : [];
            var options = units.map(function (unit) {
                return { value: String(unit.id), label: unit.slug === 'graduate-school' ? 'Graduate School' : 'College — ' + unit.name };
            });
            var selectedUnit = DD && DD.dept_id ? String(DD.dept_id) : '';
            formPop(el, 'Add a program', [
                { name: 'department', label: 'College or Graduate School', value: selectedUnit, required: true, options: options },
                { name: 'code', label: 'Short code', max: 10, required: true, placeholder: 'e.g. BSIT', hint: '2 to 10 letters or numbers (a dash is allowed).' },
                { name: 'name', label: 'Full program name', max: 255, required: true, placeholder: 'e.g. Bachelor of Science in Information Technology' }], 'Create', function (o) {
                if (!okToReload()) return;
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
        function deptOptions(x, y) {
            if (!DD || (DD.kind !== 'college' && DD.kind !== 'graduate')) return;
            var hidden = !!DD.archived;
            var toggle = h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', on: { click: function () {
                closePop(); if (!okToReload()) return;
                run(hidden ? 'Showing…' : 'Hiding…', function () {
                    return sendContent({ action: 'archive_department', dept_id: DD.dept_id, archived: hidden ? '0' : '1' }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor(r.message || 'Done.', routeNow());
                    });
                });
            } } }, [icon(hidden ? 'eye' : 'eye-slash'), hidden ? ' Show on the website' : ' Hide from the website']);
            var del = h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: function () {
                closePop();
                if (!window.confirm('Delete “' + DD.dept_name + '” for good?\n\nIt is removed from the website straight away. Its programs stay but lose this department. This cannot be undone.')) return;
                if (!okToReload()) return;
                run('Deleting…', function () {
                    return sendContent({ action: 'delete_department', dept_id: DD.dept_id }).then(function (r) {
                        if (!r.ok) { toast(r.error, 'error'); return; }
                        reloadEditor('Department deleted.', { page: 'college-departments', sub: '' });
                    });
                });
            } } }, [icon('trash'), ' Delete this department']);
            openPop(h('div', { class: 've-pop-form' }, [h('div', { class: 've-pop-label', text: 'Department options' }),
                h('small', { class: 've-pop-hint', text: hidden ? 'This department is hidden from visitors.' : 'This department is showing on the website.' }), toggle, del]), x, y);
        }
        function saveDept() {
            var errs = [], did = 0, p = Promise.resolve();
            if (BD && (Object.keys(bChange).length || Object.keys(bFiles).length)) p = p.then(function () {
                return sendContent({ action: 'save_basic_education_content', page: BD.page, texts: JSON.stringify(bChange) }, bFiles).then(function (r) {
                    if (r.ok) { did++; bChange = {}; bFiles = {}; }
                    else errs.push('Basic Education: ' + r.error);
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
            if (dFiles.logo || dRemove.logo) p = p.then(function () {
                var f = { action: 'save_department_media', dept_id: DD.dept_id };
                if (dRemove.logo && !dFiles.logo) f.remove_logo = '1';
                return sendContent(f, dFiles.logo ? { logo: dFiles.logo } : {}).then(function (r) {
                    if (r.ok) { did++; delete dFiles.logo; delete dRemove.logo; if (r.department) Object.keys(r.department).forEach(function (k) { DD[k] = r.department[k]; }); }
                    else errs.push('Logo: ' + r.error);
                });
            });
            return p.then(function () { return { did: did, errs: errs }; });
        }

        // ── programs ──
        function shortName(n) { var s = String(n).replace(/^Bachelor of (?:Science|Arts)?\s*(?:in\s+)?/i, ''); return s !== '' ? s : String(n); }
        function pMetaSet(k, v, base) { if (String(v) === String(base)) delete pMeta[k]; else pMeta[k] = v; }
        function metaVal(k) { return own(pMeta, k) ? pMeta[k] : (k === 'name' ? PD.name : k === 'description' ? PD.description : k === 'theme' ? PD.theme : k === 'theme_custom' ? PD.theme_custom : k === 'core_enabled' ? PD.core_enabled : PD.published); }
        function editProgName() {
            var el = S.doc.querySelector('[data-ve-pick="prog-name"]'); if (!el || !PD) return;
            formPop(el, 'Program name', [
                { name: 'name', label: 'Full program name', value: metaVal('name'), max: 255, required: true },
                { name: 'desc', label: 'Short description (shown on the department page cards)', value: metaVal('description'), max: 5000, multiline: true }], 'Apply', function (o) {
                pMetaSet('name', o.name, PD.name); pMetaSet('description', o.desc, PD.description);
                $$('[data-ve-pick="prog-name"]', S.doc).forEach(function (n) { n.textContent = n.tagName === 'H1' ? shortName(o.name).toUpperCase() : o.name; });
                renderToolbar(); chrome();
            });
        }
        function postItems(type, rows) {
            var fields = { action: 'save_program_items', program_id: PD.program_id, item_type: type,
                items: JSON.stringify(rows.map(function (r) { return { item_id: r.item_id || 0, title: r.title || '', subtitle: r.subtitle || '', body: r.body || '', icon: r.icon || '', letter: r.letter || '', remove_image: !!r.removeImg }; })) };
            var files = {};
            rows.forEach(function (r, n) { if (r.file) files['file_' + n] = r.file; });
            return send(PROG_URL, fields, files);
        }
        /** Adding, moving and deleting a card save straight away (the order and number of cards change the page), then the page refreshes. */
        function structural(type, rows, msg) {
            if (!okToReload(rowsChanged(type) ? 1 : 0)) return;
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
        function deleteItem(r) {
            var rows = cardRowsOf(r.type).slice(), row = rows[r.i];
            if (!window.confirm('Delete “' + (row && row.title || 'this card') + '” for good?\n\nIt is removed from the website straight away. This cannot be undone.')) return;
            rows.splice(r.i, 1);
            structural(r.type, rows, 'Deleted.');
        }
        var MATERIAL_ICONS = ['school', 'star', 'workspace_premium', 'verified', 'groups', 'menu_book', 'lightbulb', 'emoji_events', 'volunteer_activism', 'favorite', 'handshake', 'public', 'health_and_safety', 'psychology', 'science', 'computer', 'auto_stories', 'book', 'library_books', 'local_library', 'history_edu', 'draw', 'edit_note', 'description', 'article', 'assignment', 'assignment_turned_in', 'fact_check', 'checklist', 'quiz', 'grading', 'rule', 'task_alt', 'check_circle', 'done_all', 'military_tech', 'social_leaderboard', 'leaderboard', 'trending_up', 'insights', 'analytics', 'bar_chart', 'monitoring', 'query_stats', 'timeline', 'show_chart', 'pie_chart', 'engineering', 'construction', 'architecture', 'foundation', 'apartment', 'domain', 'home_work', 'location_city', 'bridge', 'carpenter', 'plumbing', 'electrical_services', 'bolt', 'power', 'hvac', 'water_drop', 'water', 'opacity', 'eco', 'park', 'forest', 'nature', 'spa', 'recycling', 'solar_power', 'wind_power', 'energy_savings_leaf', 'build', 'handyman', 'hardware', 'precision_manufacturing', 'settings', 'settings_suggest', 'tune', 'design_services', 'straighten', 'square_foot', 'ruler_triangle', 'calculate', 'functions', 'biotech', 'medication', 'medical_services', 'vaccines', 'monitor_heart', 'stethoscope', 'healing', 'local_hospital', 'sports_gymnastics', 'fitness_center', 'sports_soccer', 'sports_basketball', 'directions_run', 'code', 'terminal', 'developer_mode', 'memory', 'developer_board', 'smart_toy', 'robot_2', 'hub', 'lan', 'cloud', 'dns', 'storage', 'security', 'shield', 'lock', 'key', 'verified_user', 'admin_panel_settings', 'policy', 'gavel', 'balance', 'business_center', 'work', 'badge', 'corporate_fare', 'store', 'storefront', 'paid', 'payments', 'account_balance', 'savings', 'credit_card', 'receipt_long', 'request_quote', 'inventory_2', 'local_shipping', 'flight_takeoff', 'directions_car', 'directions_boat', 'train', 'person', 'person_celebrate', 'diversity_3', 'diversity_1', 'group_add', 'supervisor_account', 'support_agent', 'record_voice_over', 'forum', 'chat', 'mail', 'call', 'campaign', 'newspaper', 'podcasts', 'mic', 'videocam', 'photo_camera', 'palette', 'brush', 'color_lens', 'music_note', 'theater_comedy', 'piano', 'language', 'translate', 'travel_explore', 'explore', 'map', 'flag', 'globe_asia', 'sunny', 'nightlight', 'cloud_queue', 'rocket_launch', 'rocket', 'satellite_alt', 'public_off', 'bookmark', 'label', 'sell', 'workspace', 'local_activity', 'celebration', 'cake', 'redeem', 'card_giftcard', 'thumb_up', 'sentiment_satisfied', 'mood', 'self_improvement', 'psychology_alt', 'neurology', 'lightbulb_circle', 'tips_and_updates', 'auto_awesome', 'stars', 'grade', 'new_releases', 'schedule', 'calendar_month', 'event', 'today', 'timer', 'hourglass_bottom', 'update', 'history', 'pending_actions', 'alarm', 'visibility', 'search', 'manage_search', 'filter_alt', 'sort', 'share', 'link', 'attach_file', 'cloud_upload', 'download', 'upload', 'restaurant', 'local_cafe', 'fastfood', 'cooking', 'bakery_dining', 'local_dining', 'pets', 'child_care', 'family_restroom', 'escalator_warning', 'accessible', 'elderly', 'cottage', 'house', 'diversity_2', 'groups_2', 'groups_3', 'connect_without_contact', 'interpreter_mode'];
        /** The editor page itself does not load the Material Symbols font (only the preview does), so load it here and style the picker. */
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
            var input = h('input', { class: 'form-control form-control-sm', type: 'text', maxlength: '60', placeholder: 'material:school or bi:mortarboard' });
            input.value = row.icon || '';
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(input.value.trim()); } });
            ensureMaterialIcons();
            var curName = /^material:/i.test(row.icon || '') ? String(row.icon).slice(9) : '';
            var grid = h('div', { class: 've-micon-grid', role: 'listbox', 'aria-label': 'Choose an icon' }, MATERIAL_ICONS.map(function (n) {
                var label = n.replace(/_/g, ' ');
                return h('button', { type: 'button', class: 've-micon-btn' + (curName === n ? ' is-on' : ''), title: label, 'aria-label': label, 'data-n': n, on: { click: function () { apply('material:' + n); } } },
                    [h('span', { class: 'material-symbols-outlined ve-micon-glyph', 'aria-hidden': 'true', text: n })]);
            }));
            var search = h('input', { class: 'form-control form-control-sm', type: 'search', placeholder: 'Search ' + MATERIAL_ICONS.length + ' icons (e.g. book, bridge, code)…', 'aria-label': 'Search icons' });
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase().replace(/\s+/g, '_'), shown = 0;
                Array.prototype.forEach.call(grid.children, function (b) {
                    var hit = !q || b.getAttribute('data-n').indexOf(q) !== -1; b.style.display = hit ? '' : 'none'; if (hit) shown++;
                });
                empty.style.display = shown ? 'none' : '';
            });
            var empty = h('small', { class: 've-pop-hint', text: 'No icon matches. You can type any Material Symbols name below.' }); empty.style.display = 'none';
            popBelowToolbar(h('div', { class: 've-pop-form' }, [h('label', { class: 've-pop-label', text: 'Choose an icon' }), search, grid, empty,
                h('label', { class: 've-pop-label', text: 'or type an icon name' }), input,
                h('small', { class: 've-pop-hint', text: 'material:name (Material Symbols) or bi:name (Bootstrap Icons).' }),
                h('div', { class: 've-pop-row' }, [h('button', { type: 'button', class: 'btn btn-sm btn-gnc', text: 'Use this name', on: { click: function () { apply(input.value.trim()); } } })])]), null, true);
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
                if (f.size > 10 * MB) { toast('That picture is over 10 MB. Please choose a smaller one.', 'error'); return; }
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
            box.appendChild(h('label', { class: 've-pop-label', text: 'Curriculum PDF' }));
            box.appendChild(h('div', { class: 've-pop-row' }, [
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: PD.pdf_name ? 'Replace PDF' : 'Upload PDF', on: { click: function () {
                    pickFile('application/pdf,.pdf', function (file) {
                        if (file.size > 25 * MB) { toast('That PDF is over 25 MB.', 'error'); return; }
                        run('Uploading PDF…', function () {
                            return send(PROG_URL, { action: 'save_program_pdf', program_id: PD.program_id }, { pdf: file }).then(function (r) {
                                if (!r.ok) { toast(r.error, 'error'); return; }
                                closePop(); reloadEditor('Curriculum PDF saved.', routeNow());
                            });
                        });
                    });
                } } }),
                PD.pdf_url ? h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: 'Remove PDF', on: { click: function () {
                    if (!window.confirm('Remove the curriculum PDF from this program?')) return;
                    run('Removing PDF…', function () {
                        return send(PROG_URL, { action: 'save_program_pdf', program_id: PD.program_id, remove_pdf: '1' }).then(function (r) {
                            if (!r.ok) { toast(r.error, 'error'); return; }
                            closePop(); reloadEditor('Curriculum PDF removed.', routeNow());
                        });
                    });
                } } }) : null
            ]));
            box.appendChild(h('small', { class: 've-pop-hint', text: PD.pdf_name || 'No curriculum PDF is attached.' }));
            box.appendChild(h('small', { class: 've-pop-hint', text: 'These apply when you press Publish.' }));
            box.appendChild(h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', on: { click: function () {
                closePop();
                if (!window.confirm('Delete “' + PD.name + '” for good?\n\nThe whole page is removed from the website straight away. This cannot be undone.')) return;
                if (!okToReload()) return;
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
            var details = Object.keys(pChange).length > 0 || Object.keys(pMeta).length > 0;
            if (details) p = p.then(function () {
                var f = { action: 'save_program_details', program_id: PD.program_id, program_name: metaVal('name'), description: metaVal('description'), dept_id: PD.dept_id,
                    theme: metaVal('theme'), theme_custom: metaVal('theme_custom'), core_values_enabled: metaVal('core_enabled') ? '1' : '', is_published: metaVal('published') ? '1' : '' };
                Object.keys(PD.fields).forEach(function (k) { f['c_' + k] = own(pChange, k) ? pChange[k] : pBase(k); });
                return send(PROG_URL, f).then(function (r) {
                    if (r.ok) {
                        did++;
                        Object.keys(pChange).forEach(function (k) { PD.content[k] = pChange[k]; });
                        ['name', 'description', 'theme', 'theme_custom', 'core_enabled', 'published'].forEach(function (k) { if (own(pMeta, k)) PD[k] = pMeta[k]; });
                        pChange = {}; pMeta = {};
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
            return p.then(function () { return { did: did, errs: errs }; });
        }

        var A = {
            mount: function (doc) {
                resetPending();
                LISTS.banner.data = readData(doc, 'veBannerData');
                LISTS.facility.data = readData(doc, 'veFacData');
                DD = readData(doc, 'veDeptData'); PD = readData(doc, 'veProgData');
                BD = readData(doc, 'veBasicData');
                mode = DD ? 'dept' : PD ? 'prog' : BD ? 'basic' : (doc.querySelector('[data-ve-add="department"]') ? 'deptlist' : '');
                var addProgramBtn = $('#veAddProgramBtn');
                if (addProgramBtn && !addProgramBtn.__veBound) {
                    addProgramBtn.__veBound = true;
                    addProgramBtn.addEventListener('click', function () { addProgram(addProgramBtn); });
                }
                if (DD || PD || BD) bindEditing(doc);
                renderStrip();
                syncSub();
                return false;
            },
            pick: function (t) {
                if (!t || !t.closest) return null;
                var fe = fieldOf(t);
                if (fe) {
                    if (!fe.__ve) return null;
                    var it0 = fe.getAttribute('data-ve-field') === 'prog-item' ? fe.closest('[data-ve-item]') : null;
                    return { kind: 'field', el: fe, item: it0 ? it0.getAttribute('data-ve-item') : null, prevent: false };
                }
                var basicImage = t.closest('[data-ve-basic-image]');
                if (basicImage && BD) return { kind: 'basic-image', el: basicImage, key: basicImage.getAttribute('data-ve-basic-image'), prevent: true };
                var basicLogo = t.closest('[data-ve-basic-logo-id]');
                if (basicLogo && BD) return { kind: 'basic-logo', el: basicLogo, id: +basicLogo.getAttribute('data-ve-basic-logo-id'), prevent: true };
                var img = t.closest('[data-ve-img]');
                if (img && DD) { var w = img.getAttribute('data-ve-img'); if (w === 'dept-logo' || w === 'dean-photo') return { kind: 'dimg', el: img, which: w, prevent: true }; }
                var pk = t.closest('[data-ve-pick]');
                if (pk) {
                    var pv = pk.getAttribute('data-ve-pick');
                    if (pv === 'dept-facebook' && DD) { editFacebook(); return { kind: 'none' }; }
                    if (pv === 'prog-name' && PD) { editProgName(); return { kind: 'none' }; }
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
                if (go) return { kind: 'goto', el: go, url: go.getAttribute('data-ve-goto'), label: go.getAttribute('data-ve-label') || '', prevent: true };
                return null;
            },
            toolbar: function (sel) {
                var it = [];
                if (sel.kind === 'field') {
                    var v = sel.el.__ve || {}, len = readField(sel.el).length;
                    it.push({ type: 'chip', icon: 'pencil', text: (v.label || 'Text') + (v.max ? '  ·  ' + len + ' / ' + v.max : '') });
                    if (sel.el.getAttribute('data-ve-field') === 'dept-about') it.push({ type: 'chip', icon: 'info-circle', text: 'Blank line = new paragraph', title: 'Plain text. A line ending in “:” starts a list heading and lines starting with “- ” become bullets.' });
                    if (sel.item) itemActions(it, sel.item);
                } else if (sel.kind === 'card') {
                    var r0 = itemRef(sel.item);
                    it.push({ type: 'chip', icon: 'card-text', text: 'Click the text to edit it' });
                    itemActions(it, sel.item);
                    if (!r0) it.length = 0;
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
                    var logo = sel.which === 'dept-logo', key = logo ? 'logo' : 'photo';
                    var has = (!!(logo ? DD.logo_url : DD.dean_photo_url) && !dRemove[key]) || !!dFiles[key];
                    it.push({ type: 'chip', icon: 'image', text: logo ? 'Department logo' : 'Head of Office photo' });
                    it.push({ icon: 'image', label: has ? 'Replace' : 'Add picture', primary: true, run: function () {
                        pickFile(logo ? 'image/*' : 'image/jpeg,image/png,image/webp', function (f) { dFiles[key] = f; delete dRemove[key]; showPic(sel.el, previewUrl(f)); renderToolbar(); chrome(); }, !logo);
                    } });
                    if (has) it.push({ icon: 'trash', title: 'Remove the picture', danger: true, run: function () { dRemove[key] = true; delete dFiles[key]; sel.el.style.opacity = '.25'; renderToolbar(); chrome(); } });
                    if (dFiles[key]) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'New picture, not saved yet' });
                    if (dRemove[key]) it.push({ type: 'chip', tone: 'warn', icon: 'pencil', text: 'Will be removed' });
                } else if (sel.kind === 'goto') {
                    it.push({ type: 'chip', icon: 'mortarboard', text: clip(sel.label || 'Program', 40) });
                    it.push({ icon: 'pencil-square', label: 'Edit this program', primary: true, title: 'Open this program’s page to edit it', run: function () { openPage(sel.url); } });
                }
                return it;
            },
            onLink: function (a) {
                var u; try { u = new URL(a.getAttribute('href') || '', S.win.location.href); } catch (e) { return false; }
                if (u.origin !== window.location.origin || u.pathname.indexOf('/pages/academics/') !== 0) return false;
                openPage(u.pathname + u.search); return true;
            },
            dirtyCount: function () { return pendingCount(); },
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
                if (mode === 'dept' || mode === 'prog' || mode === 'basic') return { secondary: null, primary: { label: 'Publish', icon: 'cloud-upload', disabled: pendingCount() === 0, title: 'Your changes go live on the website as soon as you publish.' } };
                return { secondary: null, primary: C.hasDrawer ? { label: 'More settings', icon: 'sliders', title: 'Open the extra settings for this page' } : null };
            },
            runPrimary: function () {
                if (mode !== 'dept' && mode !== 'prog' && mode !== 'basic') { openDrawer(); return; }
                if (!pendingCount()) return;
                if (!window.confirm('Publish your changes to the live website?')) return;
                run('Publishing…', function () {
                    return (mode === 'prog' ? saveProg() : saveDept()).then(function (r) {
                        r.errs.forEach(function (m) { toast(m, 'error'); });
                        if (r.did && !r.errs.length) reloadEditor('Published. Your changes are live on the website.', routeNow());
                        else if (r.did) toast('Some changes were saved. Fix the problem above and publish again.', 'ok');
                    });
                });
            }
        };
        return A;
    }

    // ══════════════════ ADAPTER: News, announcements & events (the homepage feed; the existing editors open in the side panel) ══════════════════
    function FeedAdapter() {
        var A = {
            mount: function () { return false; },
            pick: function (t) { return postPick(t); },
            toolbar: function (sel) { return sel.kind === 'post' ? postToolbar(sel) : []; },
            onLink: function () { return false; },
            dirtyCount: function () { return 0; },
            status: function () { return { text: 'Click a news item, announcement or event to edit it here.', tone: 'ok' }; },
            actions: function () { return { secondary: null, primary: { label: 'Posts', icon: 'megaphone', title: 'Open a list of news, announcements or events' } }; },
            runPrimary: function () { postsMenu(Math.max(8, window.innerWidth - 360), 64); }
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
        if (S.adapter.dirtyCount() === 0) loadFrame(S.url, true);       // show whatever the panel just saved
        else if (wasEmbed) toast('Posts you saved are live. Publish or cancel your edits on this page, then the preview shows them.', 'ok');
    }
    if (drawer) {
        $$('[data-ve-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });
        D.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden && !$('.modal.show')) closeDrawer(); });
    }

    // ══════════════════ start ══════════════════
    var kind = C.page.kind;
    S.adapter = kind === 'home' ? HomeAdapter() : kind === 'admin' ? AdminAdapter() : kind === 'sections' ? SectionsAdapter() : kind === 'feed' ? FeedAdapter() : EntityAdapter();
    S.url = C.page.url;

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
    if (sub) sub.addEventListener('change', function () {
        if (!guardDirty()) { return; }
        loadFrame(sub.value);
    });
    var addFirst = $('#veAddFirst'); if (addFirst) addFirst.addEventListener('click', function () { var d = $('#veDock .ve-add-slide'); if (d) d.click(); });
    window.addEventListener('resize', function () { if (S.sel) placeTb(true); });

    // Bootstrap modals opened from inside the side panel must sit above the editor, so lift them to <body>.
    D.addEventListener('show.bs.modal', function (e) { var m = e.target; if (m && m.parentNode !== D.body) D.body.appendChild(m); });

    window.VE = { state: S, adapter: function () { return S.adapter; }, cleanHtml: cleanHtml };   // handy for support and tests
    setStatus('Opening the page…', 'busy');
    if (!S.url && S.adapter.startFallback) S.adapter.startFallback(); else loadFrame(S.url);
})();
