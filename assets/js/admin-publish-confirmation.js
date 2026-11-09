(() => {
    'use strict';
    const endpoint = '/admin/action/publish-confirmation-handler.php';
    const originalFetch = window.fetch.bind(window);
    const csrf = () => document.querySelector('meta[name="cms-csrf"]')?.content || '';
    let activeToken = null, pending = null;
    const release = token => {
        if (!token) return;
        originalFetch(endpoint, {method:'POST', credentials:'same-origin', keepalive:true,
            body:new URLSearchParams({action:'end', csrf_token:csrf(), publish_token:token})}).catch(() => {});
    };
    function confirmPassword() {
        if (pending) return pending;
        pending = new Promise(resolve => {
            const previous = document.activeElement;
            const dialog = document.createElement('dialog');
            dialog.className = 'gnc-dialog';
            dialog.setAttribute('aria-labelledby', 'publish-password-title');
            dialog.innerHTML = '<form class="gnc-dialog-content"><h2 id="publish-password-title">Confirm publication</h2><p>Enter your current Super Admin password to publish these changes.</p><label class="form-label mt-3" for="publish-password">Current password</label><input class="form-control" id="publish-password" type="password" autocomplete="current-password" maxlength="4096" required><p class="text-danger mt-2" role="alert" data-publish-error></p><div class="gnc-dialog-actions"><button type="button" class="gnc-dialog-cancel">Keep editing</button><button type="submit" class="gnc-dialog-accept">Confirm password</button></div></form>';
            const form = dialog.querySelector('form'), input = dialog.querySelector('input');
            const cancel = dialog.querySelector('button[type="button"]'), accept = dialog.querySelector('button[type="submit"]');
            const error = dialog.querySelector('[data-publish-error]');
            let token = null, busy = false;
            cancel.addEventListener('click', () => dialog.close());
            dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
            form.addEventListener('submit', async event => {
                event.preventDefault(); event.stopImmediatePropagation();
                if (busy) return;
                busy = true; cancel.disabled = accept.disabled = true; error.textContent = '';
                try {
                    const body = new URLSearchParams({csrf_token:csrf(), password:input.value});
                    input.value = '';
                    const response = await originalFetch(endpoint, {method:'POST', body, credentials:'same-origin', signal:AbortSignal.timeout(30000)});
                    body.delete('password');
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.error || 'Could not confirm your password.');
                    token = result.token; dialog.close();
                } catch (failure) { error.textContent = failure.message || 'Could not confirm your password. Try again.'; input.focus(); }
                finally { busy = false; cancel.disabled = accept.disabled = false; }
            });
            dialog.addEventListener('close', () => {
                input.value = ''; dialog.remove();
                if (previous?.isConnected) previous.focus({preventScroll:true});
                resolve(token);
            }, {once:true});
            document.body.appendChild(dialog); dialog.showModal(); input.focus();
        }).finally(() => { pending = null; });
        return pending;
    }
    window.AdminPublish = {
        begin: async () => { activeToken = await confirmPassword(); return !!activeToken; },
        end: () => { const token = activeToken; activeToken = null; release(token); }
    };
    window.fetch = async function(input, options) {
        const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
        if (url.origin !== location.origin || url.pathname === endpoint) return originalFetch(input, options);
        const request = token => {
            const init = {...options};
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            if (token) headers.set('X-GNCI-Publish-Token', token);
            init.headers = headers;
            return originalFetch(input instanceof Request ? input.clone() : input, init);
        };
        const response = await request(activeToken);
        if (response.status !== 428) return response;
        const result = await response.clone().json().catch(() => ({}));
        if (!result.reauth_required) return response;
        // A batch starts before its first write; never prompt midway through it.
        if (activeToken) return response;
        const token = await confirmPassword();
        if (!token) return response;
        try { return await request(token); } finally { release(token); }
    };
    const replay = new WeakSet(), authorizedForms = new WeakSet();
    document.addEventListener('formdata', event => { authorizedForms.delete(event.target); });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (form.method.toLowerCase() !== 'post') return;
        if (form.closest('.gnc-dialog') || replay.has(form) || authorizedForms.has(form)) return;
        const status = form.querySelector('[name="status"]')?.value;
        const action = event.submitter?.name === 'action' ? event.submitter.value : form.querySelector('[name="action"]')?.value;
        if (status !== 'published' && !['publish','approve'].includes(action)) return;
        event.preventDefault(); event.stopImmediatePropagation();
        const token = await confirmPassword();
        if (!token || !form.isConnected) return;
        let field = form.querySelector('[name="publish_token"]');
        if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'publish_token'; form.appendChild(field); }
        field.value = token;
        authorizedForms.add(form);
        replay.add(form);
        try { form.requestSubmit(event.submitter || undefined); } finally { replay.delete(form); }
    }, true);
})();
