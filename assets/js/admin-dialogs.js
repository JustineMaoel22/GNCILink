(function () {
    'use strict';
    var queue = Promise.resolve();
    function show(options) {
        var task = queue.then(function () {
            return new Promise(function (resolve) {
                var previous = document.activeElement;
                var dialog = document.createElement('dialog');
                dialog.className = 'gnc-dialog' + (options.danger ? ' gnc-dialog-danger' : '');
                dialog.setAttribute('aria-labelledby', 'gncDialogTitle');
                dialog.setAttribute('aria-describedby', 'gncDialogMessage');
                function element(tag, className, text) {
                    var el = document.createElement(tag); el.className = className;
                    if (text !== undefined) el.textContent = text;
                    return el;
                }
                var content = element('div', 'gnc-dialog-content');
                var icon = element('span', 'gnc-dialog-icon', options.danger ? '!' : '?');
                icon.setAttribute('aria-hidden', 'true'); content.appendChild(icon);
                var title = element('h2', '', options.title); title.id = 'gncDialogTitle'; content.appendChild(title);
                var message = element('p', '', options.message); message.id = 'gncDialogMessage'; content.appendChild(message);
                var actions = element('div', 'gnc-dialog-actions');
                function button(text, className, value) {
                    var el = element('button', className, text); el.type = 'button';
                    el.addEventListener('click', function () { dialog.close(value); });
                    actions.appendChild(el); return el;
                }
                if (!options.notice) button(options.cancelText || 'Cancel', 'gnc-dialog-cancel', '').autofocus = true;
                var accept = button(options.confirmText || 'Continue', 'gnc-dialog-accept', 'confirm');
                if (options.notice) accept.autofocus = true;
                content.appendChild(actions); dialog.appendChild(content);
                dialog.addEventListener('click', function (event) {
                    if (event.target !== dialog) return;
                    var rect = dialog.getBoundingClientRect();
                    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
                });
                dialog.addEventListener('close', function () {
                    var accepted = dialog.returnValue === 'confirm'; dialog.remove();
                    if (previous && previous.isConnected) previous.focus({ preventScroll: true });
                    resolve(accepted);
                }, { once: true });
                document.body.appendChild(dialog); dialog.showModal();
            });
        });
        queue = task.catch(function () {});
        return task;
    }
    function confirm(message, options) {
        message = String(message);
        var action = /^(delete|remove|discard|throw away|undo|hide|approve)\b/i.exec(message);
        var label = action ? ({ delete: 'Delete', remove: 'Remove', discard: 'Discard changes', 'throw away': 'Discard changes', undo: 'Undo changes', hide: 'Hide', approve: 'Approve and publish' }[action[1].toLowerCase()]) : 'Continue';
        return show(Object.assign({ title: action ? label + '?' : 'Please confirm', message: message, confirmText: label,
            danger: !!(action && /^(delete|remove|discard|throw away|undo)$/i.test(action[1])) }, options || {}));
    }
    window.AdminDialogs = {
        confirm: confirm,
        alert: function (message, options) { return show(Object.assign({ title: 'Notice', message: String(message), confirmText: 'OK', notice: true }, options || {})); }
    };
})();
