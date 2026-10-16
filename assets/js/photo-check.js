/**
 * Photo check popup (Content Editor).
 *
 * When someone picks a photo for a PERSON (Administration cards, Head of Office /
 * dean, President) this sends it to /admin/action/photo-check-handler.php first.
 *   - Not a person      -> popup "This image is not a person", the file is cleared
 *   - AI-generated      -> popup "This image is AI-generated", the file is cleared
 *   - OK -> continue; unavailable -> ask the user to retry
 *
 * It listens in the capture phase and holds back the editor's own "change"
 * handlers until the check passes, so previews never show a rejected photo.
 * No edits to the manager components are needed. The server re-checks on save.
 */
(function () {
    'use strict';

    var HANDLER = new URL('action/photo-check-handler.php', window.location.href).href;
    console.info('[photo-check] script loaded');
    var PERSON_NAMES = { photo: 1, dean_photo: 1 };    // <input type="file" name="...">
    var PERSON_IDS   = { 'adm-person-photo': 1, 'adm-office-photo': 1 };   // Administration modals (no name attribute)

    function csrf() {
        return (window.CE_CONFIG && window.CE_CONFIG.csrf) ||
               (window.SLIDE_EDITOR_CONFIG && window.SLIDE_EDITOR_CONFIG.csrfToken) || '';
    }

    function isPersonInput(el) {
        return el && el.tagName === 'INPUT' && el.type === 'file' &&
               (PERSON_NAMES[el.name] === 1 || PERSON_IDS[el.id] === 1 || el.hasAttribute('data-photo-check'));
    }

    // ── popup ──
    var css = document.createElement('style');
    css.textContent =
        '.pc-back{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:20000;display:flex;align-items:center;justify-content:center;padding:16px}' +
        '.pc-box{background:#fff;color:#222;border-radius:12px;max-width:420px;width:100%;padding:24px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.35);font-family:inherit}' +
        '.pc-icon{font-size:42px;line-height:1;margin-bottom:8px}' +
        '.pc-box h2{font-size:20px;margin:0 0 8px}' +
        '.pc-box p{margin:0 0 18px;color:#555;font-size:15px}' +
        '.pc-btn{border:0;border-radius:8px;padding:9px 22px;font-size:15px;font-weight:600;background:#1f5e2c;color:#fff;cursor:pointer}' +
        '.pc-spin{width:34px;height:34px;border:4px solid #ddd;border-top-color:#1f5e2c;border-radius:50%;margin:0 auto 12px;animation:pcs .8s linear infinite}' +
        '@keyframes pcs{to{transform:rotate(360deg)}}';
    document.head.appendChild(css);

    var overlay = null, lastFocus = null;

    function close() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        overlay = null;
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    }

    function open(html, withButton) {
        close();
        lastFocus = document.activeElement;
        overlay = document.createElement('div');
        overlay.className = 'pc-back';
        overlay.innerHTML = '<div class="pc-box" role="alertdialog" aria-modal="true">' + html +
            (withButton ? '<button type="button" class="pc-btn">OK</button>' : '') + '</div>';
        document.body.appendChild(overlay);
        var b = overlay.querySelector('.pc-btn');
        if (b) { b.addEventListener('click', close); b.focus(); }
    }

    function notice(text) {
        var n = document.createElement('div');
        n.setAttribute('role', 'status');
        n.style.cssText = 'position:fixed;right:16px;bottom:16px;max-width:380px;z-index:20001;background:#fff3cd;color:#664d03;' +
            'border:1px solid #ffecb5;border-radius:10px;padding:10px 14px;font-size:13px;box-shadow:0 6px 20px rgba(0,0,0,.2)';
        n.textContent = text;
        document.body.appendChild(n);
        setTimeout(function () { if (n.parentNode) n.parentNode.removeChild(n); }, 12000);
    }

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function showRejected(reason, message) {
        var title = reason === 'ai_generated' ? 'This image appears AI-generated'
                  : (reason === 'not_person' ? 'This image is not a person' : 'Photo could not be verified');
        open('<div class="pc-icon">' + (reason === 'ai_generated' ? '&#129302;' : (reason === 'unavailable' ? '&#9203;' : '&#128683;')) + '</div>' +
             '<h2>' + esc(title) + '</h2><p>' + esc(message || 'Please choose a real photo of the person.') + '</p>', true);
    }

    // ── interception ──
    document.addEventListener('change', function (ev) {
        var input = ev.target;
        if (!isPersonInput(input)) return;

        if (input.__pcPassed) { input.__pcPassed = false; return; }   // our own re-dispatch: let it through
        if (!input.files || !input.files.length) return;              // file was cleared: nothing to check

        ev.stopImmediatePropagation();                                // hold the editor's handlers

        var selectedFile = input.files[0];
        if (selectedFile.size > 5 * 1024 * 1024) {
            input.value = '';
            showRejected('invalid_image', 'The photo is too large. Maximum size is 5 MB.');
            return;
        }

        var fd = new FormData();
        fd.append('csrf_token', csrf());
        fd.append('photo', input.files[0]);

        open('<div class="pc-spin"></div><p>Checking the photo&hellip;</p>', false);

        var ctl = window.AbortController ? new AbortController() : null;
        var timer = ctl ? setTimeout(function () { ctl.abort(); }, 60000) : null;   // never keep the screen covered
        fetch(HANDLER, { method: 'POST', body: fd, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (res) {
                if (timer) clearTimeout(timer);
                if (input.files[0] !== selectedFile) { close(); return; }
                if (res && res.success && res.ok === false) {
                    console.info('[photo-check] rejected:', res.reason, res.detail);
                    input.value = '';
                    showRejected(res.reason, res.message);
                    return;
                }
                if (!res || !res.success || res.checked !== true || res.ok !== true) {
                    input.value = '';
                    showRejected('unavailable', (res && (res.error || res.message)) || 'The photo could not be checked. Please try again.');
                    return;
                }
                notice('Photo check passed. You can save your changes.');
                close();
                input.__pcPassed = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            })
            .catch(function (err) {
                if (timer) clearTimeout(timer);
                console.warn('[photo-check] could not reach the handler:', err);
                input.value = '';
                showRejected('unavailable', 'The server could not check this photo. Please try again.');
            });
    }, true);
})();
