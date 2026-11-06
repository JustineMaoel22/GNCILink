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

    var HANDLER = '/admin/action/photo-check-handler.php';
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

    function context(input) {
        if (input.__pcContext) return input.__pcContext();
        var selector = input.id === 'adm-person-photo' ? '#adm-person-name' :
            input.id === 'adm-office-photo' ? '#adm-office-head' :
            input.id === 'president-photo' ? '#president-name' : '.dean-name-input';
        var scope = input.closest('[data-dept-id]') || document;
        var field = scope.querySelector(selector);
        return { name: field ? field.value : '', key: input.dataset.profileKey || '' };
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
    css.textContent += '.pc-box{max-height:90vh;overflow:auto}.pc-compare{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0}.pc-compare img{width:100%;height:150px;object-fit:contain;background:#f5f7f5;border-radius:8px}.pc-compare figcaption{overflow-wrap:anywhere;font-size:14px}.pc-compare figure{margin:0}.pc-actions{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}.pc-box textarea{width:100%;margin:8px 0}.pc-box .pc-secondary{background:#64716a}@media(prefers-reduced-motion:reduce){.pc-spin{animation:none}}';

    var overlay = null, lastFocus = null;
    css.textContent += '.pc-compare{align-items:start}.pc-existing{display:grid;gap:16px;align-content:start}';

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
        overlay.innerHTML = '<div class="pc-box" role="alertdialog" aria-label="Personnel photo validation" aria-modal="true" tabindex="-1">' + html +
            (withButton ? '<button type="button" class="pc-btn">OK</button>' : '') + '</div>';
        document.body.appendChild(overlay);
        var b = overlay.querySelector('.pc-btn');
        if (b) { b.addEventListener('click', close); b.focus(); }
        else overlay.querySelector('.pc-box').focus();
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

    document.addEventListener('keydown', function (event) {
        if (!overlay) return;
        if (event.key === 'Escape') { event.preventDefault(); return; }
        if (event.key !== 'Tab') return;
        var items = overlay.querySelectorAll('button,textarea');
        if (!items.length) { event.preventDefault(); return; }
        var first = items[0], last = items[items.length - 1];
        if (!overlay.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }, true);

    function compareWarning(res, file, person, accept, reject, review) {
        open('<h2>Possible Duplicate Photo Detected</h2><p>' + esc(res.message) + '</p><p>Similarity is a screening result and does not prove identity.</p><div class="pc-results"></div><div class="pc-actions"></div>', false);
        var results = overlay.querySelector('.pc-results');
        if (res.coverage_notice) {
            var coverage = document.createElement('p'); coverage.textContent = res.coverage_notice; results.appendChild(coverage);
        }
        var url = URL.createObjectURL(file);
        var groups = new Map();
        (res.matches || []).forEach(function (match) {
            // Mirror the server's cross-section identity rule for presentation only.
            var identity = String(match.name || '').trim().replace(/\s+/g, ' ').toLowerCase() || match.key;
            var group = groups.get(identity);
            if (!group) { group = { match:match, profiles:new Set() }; groups.set(identity, group); }
            group.profiles.add(match.key);
            if (match.similarity > group.match.similarity) group.match = match;
        });
        function portrait(image, name) {
            var figure = document.createElement('figure'), img = document.createElement('img'), label = document.createElement('figcaption');
            img.src = image; img.alt = name; label.textContent = name;
            figure.appendChild(img); figure.appendChild(label);
            return figure;
        }
        var row = document.createElement('div'); row.className = 'pc-compare';
        var uploaded = document.createElement('div'); uploaded.className = 'pc-upload';
        uploaded.appendChild(portrait(url, person.name));
        var existing = document.createElement('div'); existing.className = 'pc-existing';
        groups.forEach(function (group) {
            var figure = portrait(group.match.image, group.match.name);
            if (group.profiles.size > 1) {
                var count = document.createElement('small');
                count.textContent = 'Used in ' + group.profiles.size + ' profiles';
                figure.appendChild(count);
            }
            existing.appendChild(figure);
        });
        row.appendChild(uploaded); row.appendChild(existing); results.appendChild(row);
        function button(label, action) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'pc-btn'; b.textContent = label;
            b.onclick = function () { URL.revokeObjectURL(url); action(); };
            overlay.querySelector('.pc-actions').appendChild(b);
            return b;
        }
        button('Replace photo', reject).focus();
        if (res.ok) button('Continue after checking', accept);
        if (!res.ok && res.can_review) {
            button('Confirm reviewed assignment', review);
        }
        if (!res.ok && !res.can_review) {
            var help = document.createElement('p'); help.textContent = 'Saving is blocked. Replace the photo or ask a Super Admin to open and review this profile.';
            results.appendChild(help);
        }
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
        var person = context(input);
        fd.append('person_name', person.name);
        fd.append('profile_key', person.key);

        function accept() {
            close();
            input.__pcPassed = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function reject() { input.value = ''; close(); }

        open('<div class="pc-spin"></div><p>Checking the photo&hellip;</p>', false);

        var ctl = window.AbortController ? new AbortController() : null;
        var timer = ctl ? setTimeout(function () { ctl.abort(); }, 60000) : null;   // never keep the screen covered
        fetch(HANDLER, { method: 'POST', body: fd, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (res) {
                if (timer) clearTimeout(timer);
                if (input.files[0] !== selectedFile) { close(); return; }
                if (res && res.success && res.reason === 'duplicate_face' && !res.reviewed) {
                    compareWarning(res, selectedFile, person, accept, reject, function () {
                        fd.set('review_duplicate', '1');
                        open('<div class="pc-spin"></div><p>Recording review&hellip;</p>', false);
                        fetch(HANDLER, { method:'POST',body:fd,credentials:'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (reviewed) {
                                if (reviewed.success && reviewed.ok && reviewed.reviewed) accept();
                                else { input.value = ''; showRejected(reviewed.reason, reviewed.error || reviewed.message); }
                            }).catch(function () { input.value = ''; showRejected('unavailable', 'The review could not be saved. Please try again.'); });
                    });
                    return;
                }
                if (res && res.success && res.ok === false) {
                    console.info('[photo-check] rejected:', res.reason, res.detail);
                    input.value = '';
                    showRejected(res.reason, res.message);
                    return;
                }
                var disabled = res && res.success === true && res.ok === true && res.reason === 'disabled';
                if (!res || !res.success || (!disabled && res.checked !== true) || res.ok !== true) {
                    input.value = '';
                    showRejected('unavailable', (res && (res.error || res.message)) || 'The photo could not be checked. Please try again.');
                    return;
                }
                if (!disabled) notice(res.coverage_notice || 'Photo check passed. You can save your changes.');
                accept();
            })
            .catch(function (err) {
                if (timer) clearTimeout(timer);
                console.warn('[photo-check] could not reach the handler:', err);
                input.value = '';
                showRejected('unavailable', 'The server could not check this photo. Please try again.');
            });
    }, true);
})();
