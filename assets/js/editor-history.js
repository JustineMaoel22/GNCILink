/* Session-local preview history. Never sends requests or stores media on the server. */
(function () {
    'use strict';
    function create(options) {
        var entries = [], index = -1, restoring = false, ready = false, scheduled = false, baselinePending = false;
        var lastTarget = null, lastTime = 0, inputTarget = null;
        var fileIds = new WeakMap(), serial = 0, media = new Map(), retiredUrls = new Set();
        function clone(value) {
            if (value == null || typeof value !== 'object') return value;
            if (value.nodeType || value === window) return undefined;
            if (value instanceof Blob) return value;
            if (Array.isArray(value)) return value.map(clone);
            var out = {};
            Object.keys(value).forEach(function (key) {
                if (!/^(?:__proto__|constructor|prototype)$/.test(key) && typeof value[key] !== 'function') out[key] = clone(value[key]);
            });
            return out;
        }
        function fingerprint(state) {
            return JSON.stringify(state, function (_, value) {
                if (value instanceof Blob) {
                    if (!fileIds.has(value)) fileIds.set(value, ++serial);
                    return {$historyFile:fileIds.get(value)};
                }
                return value;
            });
        }
        // Keep the actual nodes so removed cards, listeners and custom controls survive undo.
        // Only local, trusted DOM is retained; no saved HTML is executed on restoration.
        function tree(node) {
            return {node:node, text:node.nodeType === 3 ? node.nodeValue : null,
                attrs:node.nodeType === 1 ? Array.from(node.attributes).map(function (a) { return [a.name,a.value]; }) : null,
                children:Array.from(node.childNodes).map(tree)};
        }
        function restoreTree(saved) {
            var node = saved.node;
            if (saved.text !== null) { if (node.nodeValue !== saved.text) node.nodeValue = saved.text; return; }
            if (saved.attrs) {
                // Loading/reveal completion belongs to the live page, not to an edit.
                // Rewinding it can hide cached images forever: no new load event fires.
                var loaded = node.classList.contains('is-loaded'), visible = node.classList.contains('is-visible');
                var hero = node.classList.contains('skeleton-hero'), background = node.style.backgroundImage;
                var source = saved.attrs.find(function (pair) { return pair[0] === 'data-bg'; });
                var sameBackground = source && node.getAttribute('data-bg') === source[1];
                Array.from(node.attributes).forEach(function (a) { if (!saved.attrs.some(function (pair) { return pair[0] === a.name; })) node.removeAttribute(a.name); });
                saved.attrs.forEach(function (pair) { if (node.getAttribute(pair[0]) !== pair[1]) node.setAttribute(pair[0],pair[1]); });
                if (loaded) node.classList.add('is-loaded');
                if (visible) node.classList.add('is-visible');
                if (sameBackground && !hero && node.classList.contains('skeleton-hero')) {
                    node.classList.remove('skeleton-hero');
                    if (!node.style.backgroundImage || node.style.backgroundImage === 'none') node.style.backgroundImage = background;
                }
                // Homepage backgrounds/videos are unobserved once their lazy loader runs.
                // A snapshot taken before scrolling still has the deferred source. Resolve
                // that saved source now; reinstating the lazy marker alone leaves it blank.
                if (node.classList.contains('lazy-bg') && node.getAttribute('data-bg')) {
                    node.style.backgroundImage = 'url(' + JSON.stringify(node.getAttribute('data-bg')) + ')';
                    node.classList.remove('lazy-bg');node.removeAttribute('data-bg');
                }
                if (node.tagName === 'VIDEO' && node.classList.contains('lazy-video')) {
                    if (node.getAttribute('data-src')) { node.src=node.getAttribute('data-src');node.removeAttribute('data-src'); }
                    if (node.getAttribute('data-poster')) { node.poster=node.getAttribute('data-poster');node.removeAttribute('data-poster'); }
                    node.preload='auto';node.classList.remove('lazy-video');
                }
            }
            var children = saved.children.map(function (child) { return child.node; });
            Array.from(node.childNodes).forEach(function (child) { if (children.indexOf(child) < 0) node.removeChild(child); });
            saved.children.forEach(function (child, position) {
                if (node.childNodes[position] !== child.node) node.insertBefore(child.node,node.childNodes[position] || null);
                restoreTree(child);
            });
        }
        function rememberMedia(state, dom) {
            var text = fingerprint(state);
            function attrs(saved) { (saved.attrs || []).forEach(function (pair) { text += ' ' + pair[1]; }); saved.children.forEach(attrs); }
            attrs(dom);
            (text.match(/blob:https?:\/\/[^"'()\s\\]+/g) || []).forEach(function (url) {
                var blob = window.GNCEditorDrafts && window.GNCEditorDrafts.media(url);
                if (blob && !media.has(url)) media.set(url, {blob:blob, url:null});
            });
        }
        function renew(value) {
            if (typeof value === 'string') return value.replace(/blob:https?:\/\/[^"'()\s\\]+/g,function (url) {
                var entry = media.get(url);
                if (!entry) return url;
                if (!entry.url) { entry.url = URL.createObjectURL(entry.blob); window.GNCEditorDrafts.trackUrl(entry.url,entry.blob); }
                return entry.url;
            });
            if (!value || typeof value !== 'object' || value instanceof Blob) return value;
            Object.keys(value).forEach(function (key) { value[key] = renew(value[key]); });
            return value;
        }
        function capture(force) {
            if (!ready || restoring || options.blocked()) return;
            var state = clone(options.read()), key = fingerprint(state);
            var doc = options.document(), selection = doc.defaultView.getSelection();
            var caret = selection && selection.rangeCount ? {anchor:selection.anchorNode,anchorOffset:selection.anchorOffset,
                focus:selection.focusNode,focusOffset:selection.focusOffset,field:doc.activeElement} : null;
            if (index >= 0 && entries[index].key === key) { if (caret) entries[index].caret = caret; return; }
            var dom = tree(doc.body);
            rememberMedia(state,dom);
            var entry = {state:state, key:key, dom:dom, caret:caret};
            var now = Date.now(), merge = !force && inputTarget && inputTarget === lastTarget && now-lastTime < 700 && index > 0 && index === entries.length-1;
            entries.splice(index+1);
            if (merge) entries[index] = entry;
            else { entries.push(entry); index++; }
            if (entries.length > 51) { entries.shift(); index--; }
            lastTarget = inputTarget; lastTime = now; inputTarget = null;
            paint();
        }
        function paint() { options.buttons(index > 0, index >= 0 && index < entries.length-1); }
        function refresh() {
            if (baselinePending && !options.blocked()) { baselinePending = false; start(); }
            paint();
            if (scheduled || restoring || !ready) return;
            scheduled = true;
            queueMicrotask(function () { scheduled = false; capture(false); });
        }
        function reset() {
            ready = false; baselinePending = false; entries = []; index = -1; lastTarget = null; inputTarget = null;
            media.forEach(function (entry) { if (entry.url) retiredUrls.add(entry.url); }); media.clear();
            // A partial save may leave restored uploads in the working state. Keep those URLs live.
            var doc = options.document(), active = doc ? doc.body.innerHTML + fingerprint(clone(options.read())) : '';
            retiredUrls.forEach(function (url) { if (active.indexOf(url) < 0) { URL.revokeObjectURL(url); retiredUrls.delete(url); } });
            paint();
        }
        function start() { ready = true; capture(true); }
        function committed() { reset(); baselinePending = true; }
        function move(delta) {
            if (!ready || restoring || options.blocked()) return;
            capture(false);
            var next = index+delta;
            if (next < 0 || next >= entries.length) return;
            restoring = true;
            try {
                options.beforeRestore();
                var entry = entries[next];
                // Renew URLs whose original upload preview may have been revoked by replacement.
                function mediaAttrs(saved) { (saved.attrs || []).forEach(function (pair) { pair[1] = renew(pair[1]); }); saved.children.forEach(mediaAttrs); }
                mediaAttrs(entry.dom); restoreTree(entry.dom);
                options.write(renew(clone(entry.state)));
                index = next; lastTarget = null; inputTarget = null;
                var caret = entry.caret;
                if (caret && caret.anchor.isConnected && caret.focus.isConnected) {
                    if (caret.field && caret.field.isConnected && caret.field.isContentEditable) caret.field.focus({preventScroll:true});
                    function offset(node, value) { return Math.min(value,node.nodeType === 3 ? node.length : node.childNodes.length); }
                    options.document().defaultView.getSelection().setBaseAndExtent(caret.anchor,offset(caret.anchor,caret.anchorOffset),caret.focus,offset(caret.focus,caret.focusOffset));
                }
                // Rebinding can normalize state and URLs. This is still the same history entry.
                entry.key = fingerprint(clone(options.read()));
            } finally { restoring = false; options.refresh(); paint(); }
        }
        function bind(doc) {
            doc.addEventListener('beforeinput',function (event) {
                if (!event.target.isContentEditable) return;
                if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                    event.preventDefault(); move(event.inputType === 'historyUndo' ? -1 : 1); return;
                }
                capture(true);
                inputTarget = /^(insertText|deleteContentBackward|deleteContentForward)$/.test(event.inputType) ? event.target : null;
            },true);
            doc.addEventListener('keydown',shortcut,true);
            doc.addEventListener('compositionstart',function () { lastTarget = null; });
        }
        function shortcut(event) {
            if (!(event.ctrlKey || event.metaKey) || event.altKey || event.isComposing) return;
            var key = event.key.toLowerCase();
            if (key !== 'z' && !(key === 'y' && !event.shiftKey)) return;
            var target = event.target;
            // Popups, search boxes and independently saved forms retain native input history.
            if (target.closest && (target.closest('input,textarea,select,#veDrawer,.modal,#vePop') || (target.ownerDocument === document && target.isContentEditable))) return;
            if (!ready || options.blocked()) return;
            event.preventDefault(); event.stopImmediatePropagation(); move(key === 'y' || event.shiftKey ? 1 : -1);
        }
        return {refresh:refresh,reset:reset,committed:committed,start:start,bind:bind,shortcut:shortcut,undo:function(){move(-1);},redo:function(){move(1);}};
    }
    window.GNCEditorHistory = {create:create};
})();
