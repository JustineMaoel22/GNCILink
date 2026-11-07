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
    var overlay = null, lastFocus = null, dismissComparison = null, activeNotice = null;

    function close() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        overlay = null;
        dismissComparison = null;
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    }

    function open(html, withButton) {
        if (activeNotice) { activeNotice.remove(); activeNotice = null; }
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
        if (activeNotice) activeNotice.remove();
        var n = document.createElement('div');
        n.setAttribute('role', 'status');
        n.style.cssText = 'position:fixed;right:16px;bottom:16px;max-width:380px;z-index:20001;background:#fff3cd;color:#664d03;' +
            'border:1px solid #ffecb5;border-radius:10px;padding:10px 14px;font-size:13px;box-shadow:0 6px 20px rgba(0,0,0,.2)';
        n.textContent = text;
        document.body.appendChild(n);
        activeNotice = n;
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
        if (event.key === 'Escape') { event.preventDefault(); if (dismissComparison) dismissComparison(); return; }
        if (event.key !== 'Tab') return;
        var items = overlay.querySelectorAll('button:not(:disabled),textarea:not(:disabled)');
        if (!items.length) { event.preventDefault(); return; }
        var first = items[0], last = items[items.length - 1];
        if (!overlay.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }, true);

    function compareWarning(res, file, person, accept, reject, review) {
        open('<header class="pc-header"><span class="pc-warning-icon" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.8 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg></span>' +
            '<div><span class="pc-eyebrow">Photo verification</span><h2 id="pc-comparison-title">Possible Duplicate Photo Detected</h2>' +
            '<p id="pc-comparison-description">This photo looks similar to an existing profile photo. Compare the photos before continuing.</p></div></header>' +
            '<div class="pc-body"><div class="pc-results"></div>' +
            '<aside class="pc-guidance"><strong>A similarity match is not proof of identity.</strong><p>Check the person\u2019s name and photo to make sure this assignment is correct.</p></aside>' +
            '<div class="pc-coverage" hidden></div></div>' +
            '<footer class="pc-footer"><p class="pc-action-help"></p><div class="pc-actions"></div></footer>', false);
        var box = overlay.querySelector('.pc-box');
        box.classList.add('pc-comparison');
        box.removeAttribute('aria-label');
        box.setAttribute('aria-labelledby', 'pc-comparison-title');
        box.setAttribute('aria-describedby', 'pc-comparison-description');
        var results = box.querySelector('.pc-results');
        if (res.coverage_notice) {
            var coverage = box.querySelector('.pc-coverage');
            var heading = document.createElement('strong'), message = document.createElement('p');
            heading.textContent = 'Some photos could not be compared';
            message.textContent = res.coverage_notice;
            coverage.append(heading, message); coverage.hidden = false;
        }
        var url = URL.createObjectURL(file);
        var dismiss = document.createElement('button');
        dismiss.type = 'button'; dismiss.className = 'pc-close';
        dismiss.setAttribute('aria-label', 'Close photo comparison');
        dismiss.textContent = '\u00d7';
        dismissComparison = function () { URL.revokeObjectURL(url); reject(); };
        dismiss.onclick = dismissComparison;
        box.prepend(dismiss);
        var groups = new Map();
        (res.matches || []).forEach(function (match) {
            // Mirror the server's cross-section identity rule for presentation only.
            var identity = String(match.name || '').trim().replace(/\s+/g, ' ').toLowerCase() || match.key;
            var group = groups.get(identity);
            if (!group) { group = { match:match, profiles:new Set() }; groups.set(identity, group); }
            group.profiles.add(match.key);
            if (match.similarity > group.match.similarity) group.match = match;
        });
        function profileLabel(key) {
            return {person:'Administration', office:'Office head', dean:'Department head', president:'Homepage president'}[String(key || '').split(':')[0]] || 'Personnel profile';
        }
        function portrait(image, name, heading, detail, isExisting) {
            var figure = document.createElement('figure'), img = document.createElement('img'), caption = document.createElement('figcaption');
            figure.className = 'pc-photo-card' + (isExisting ? ' pc-photo-match' : '');
            var title = document.createElement('h3'); title.textContent = heading;
            var frame = document.createElement('div'); frame.className = 'pc-photo-frame';
            img.src = image; img.alt = heading + ': ' + name;
            img.addEventListener('error', function () {
                img.hidden = true;
                var unavailable = document.createElement('span'); unavailable.className = 'pc-photo-unavailable';
                unavailable.textContent = 'Photo unavailable'; frame.appendChild(unavailable);
            }, { once:true });
            frame.appendChild(img);
            var fullName = document.createElement('strong'); fullName.textContent = name || 'Name not available';
            var info = document.createElement('span'); info.className = 'pc-profile-info'; info.textContent = detail;
            caption.append(fullName, info);
            figure.append(title, frame, caption);
            return figure;
        }
        var row = document.createElement('div'); row.className = 'pc-compare';
        var uploaded = document.createElement('div'); uploaded.className = 'pc-upload';
        uploaded.appendChild(portrait(url, person.name, 'Uploaded Photo', profileLabel(person.key) + ' \u00b7 New upload', false));
        var existing = document.createElement('div'); existing.className = 'pc-existing';
        groups.forEach(function (group) {
            var locations = [...new Set([...group.profiles].map(profileLabel))].join(' \u00b7 ');
            var figure = portrait(group.match.image, group.match.name, 'Existing Profile Photo', locations, true);
            if (group.profiles.size > 1) {
                var count = document.createElement('small'); count.className = 'pc-profile-count';
                count.textContent = 'Used in ' + group.profiles.size + ' profiles';
                figure.querySelector('figcaption').appendChild(count);
            }
            existing.appendChild(figure);
        });
        row.append(uploaded, existing); results.appendChild(row);
        function button(label, action, secondary) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'pc-btn' + (secondary ? ' pc-secondary' : ' pc-primary'); b.textContent = label;
            b.onclick = function () {
                box.querySelectorAll('button').forEach(function (control) { control.disabled = true; });
                dismissComparison = null;
                URL.revokeObjectURL(url); action();
            };
            box.querySelector('.pc-actions').appendChild(b);
            return b;
        }
        var help = box.querySelector('.pc-action-help');
        help.textContent = 'Replace the photo to choose a different image. Your other edits will be kept.';
        button('Replace Photo', reject, false).focus();
        if (res.ok) {
            button('Continue After Checking', accept, true);
            help.textContent = 'Replace the photo, or continue after verifying the person and assignment. This does not publish your changes.';
        }
        if (!res.ok && res.can_review) {
            button('Confirm Reviewed Assignment', review, true);
            help.textContent = 'Confirm only after checking the person and assignment. Your review is recorded; changes are not published yet.';
        }
        if (!res.ok && !res.can_review) {
            var permissions = document.createElement('aside'); permissions.className = 'pc-permission';
            permissions.textContent = 'To keep this photo, ask a Super Admin to review this profile. You can replace it now.';
            box.querySelector('.pc-body').appendChild(permissions);
        }
    }

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
