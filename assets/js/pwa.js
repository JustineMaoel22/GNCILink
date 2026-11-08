/* Install/offline UX and quiet update checks. No form data is stored/replayed. */
(function () {
    'use strict';
    if (window.top !== window.self || new URLSearchParams(location.search).has('ce_preview') || !window.isSecureContext) return;
    const admin = document.querySelector('meta[name="gnci-pwa-area"]')?.content === 'admin';
    const authenticatedAdmin = admin && document.querySelector('meta[name="gnci-pwa-authenticated"]')?.content === 'true';
    const appName = admin ? 'GNCILink Admin' : 'GNCILink';
    const standalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const offlineCopy = !!document.querySelector('meta[name="gnci-offline"]') || document.body.classList.contains('gnc-offline-page');
    let deferredInstall, registration, notice, noticeKind, dialog, previousFocus, installLink;
    let promotionTimer, lastCheck = 0;
    const campusMap = !admin && document.getElementById('campus-map');
    const chatbot = !admin && document.getElementById('gncbot');
    let mapVisible = false, promotionShown = false;
    let visitorInstalled = !admin && standalone();
    try {
        // Retire the previous persistent dismissal; visits now use sessionStorage only.
        localStorage.removeItem('gncilink-install-dismissed');
        if (!admin) {
            if (visitorInstalled) localStorage.setItem('gncilink-visitor-installed','1');
            visitorInstalled = visitorInstalled || localStorage.getItem('gncilink-visitor-installed') === '1';
        }
    } catch (_) {}
    const installed = () => standalone() || visitorInstalled;
    try { promotionShown = sessionStorage.getItem('gncilink-install-shown') === '1'; } catch (_) {}
    const node = (tag, text, cls) => {
        const el = document.createElement(tag);
        if (text) el.textContent = text;
        if (cls) el.className = cls;
        return el;
    };
    const button = (text, action, cls) => {
        const el = node('button',text,cls); el.type = 'button'; el.addEventListener('click',action); return el;
    };
    function dismissPromotion() {
        promotionShown = true;
        try { sessionStorage.setItem('gncilink-install-shown','1'); } catch (_) {}
    }
    function closeNotice() {
        if (notice) notice.remove(); notice = null; noticeKind = null;
    }
    function showNotice(kind, title, text, label, action) {
        closeNotice(); noticeKind = kind;
        notice = node('aside',null,'gnc-pwa-notice'); notice.id = 'gncPwaNotice'; notice.setAttribute('aria-label',title);
        const dismiss = button('×',() => {if(kind === 'install') dismissPromotion();closeNotice();},'gnc-pwa-dismiss');
        dismiss.setAttribute('aria-label','Dismiss '+title);
        const heading = node('strong',title); heading.setAttribute('role','status');
        notice.append(dismiss,heading,node('p',text));
        const actions = node('div',null,'gnc-pwa-actions'); actions.append(button(label,action));
        notice.append(actions); document.body.append(notice);
    }
    function instructions() {
        if (dialog?.open) return;
        previousFocus = document.activeElement;
        dialog = node('dialog',null,'gnc-pwa-dialog'); dialog.id = 'gncPwaInstallHelp';
        dialog.setAttribute('aria-labelledby','gncPwaInstallTitle');
        const title = node('h2','Install '+appName); title.id = 'gncPwaInstallTitle';
        const steps = ios
            ? ['Open this website in Safari.','Tap the Share button. If it is inside the browser menu, open that menu first.','Choose Add to Home Screen, keep Open as Web App enabled if shown, then tap Add.']
            : ['Open your browser’s menu.','Look for Install '+appName+', Install web app, or Add to Home Screen. On Safari for Mac, choose File → Add to Dock.','Confirm to add '+appName+'. If installation is unavailable, use a browser that supports installed web apps.'];
        const list = node('ol'); steps.forEach(text => list.append(node('li',text)));
        const actions = node('div',null,'gnc-pwa-actions'); actions.append(button('Got it',()=>dialog.close()));
        dialog.append(title,node('p',admin ? 'Open the admin panel from your device. An internet connection and normal sign-in are required.' : 'Keep school information close, with a shortcut on your device.'),list,actions);
        dialog.addEventListener('close',()=>{dialog.remove();previousFocus?.focus();},{once:true});
        document.body.append(dialog); dialog.showModal();
    }
    async function install() {
        if (installed()) return;
        closeNotice();
        if (ios || !deferredInstall) return instructions();
        const prompt = deferredInstall; deferredInstall = null;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            if (choice?.outcome === 'dismissed') dismissPromotion();
        } catch (_) { instructions(); }
    }
    function promote() {
        clearTimeout(promotionTimer);
        if (!campusMap || !mapVisible || promotionShown || installed() || document.hidden) return;
        promotionTimer = setTimeout(()=>{
            if (!mapVisible || promotionShown || installed() || document.hidden) return;
            // Privacy/other dialogs and active typing take priority over install promotion.
            if (chatbot?.classList.contains('is-open') || document.querySelector('dialog[open],.modal.show,.offcanvas.show,#gncPrivacyBanner:not([hidden])') || document.activeElement?.matches('input,textarea,select,[contenteditable=true]')) {
                promotionTimer = setTimeout(promote,1000);return;
            }
            dismissPromotion();
            showNotice('install','GNCILink, one tap away','Add the school website to your device for quick access.','Install web app',install);
            notice.classList.add('gnc-pwa-install-notice');
            if (chatbot) {
                notice.classList.add('gnc-pwa-install-chat');
                notice.setAttribute('aria-label','Installation tip from Cian');
                notice.insertBefore(node('span','A tip from Cian','gnc-pwa-tip-from'),notice.querySelector('strong'));
            }
        },2000);
    }
    if (campusMap && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries=>{
            mapVisible = entries.some(entry=>entry.isIntersecting && entry.intersectionRatio >= 0.15);
            promote();
        },{threshold:[0,0.15]});
        observer.observe(campusMap);
        document.addEventListener('visibilitychange',promote);
    }
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();deferredInstall=event;
        // A fresh install offer is evidence the visitor app is installable again.
        if (!admin) {visitorInstalled=false;try {localStorage.removeItem('gncilink-visitor-installed');} catch (_) {}}
        promote();
    });
    window.addEventListener('appinstalled',()=>{
        if (!admin) {visitorInstalled=true;try {localStorage.setItem('gncilink-visitor-installed','1');} catch (_) {}}
        closeNotice();if(installLink)installLink.hidden=true;deferredInstall=null;
    });
    window.addEventListener('storage',event=>{
        if (!admin && event.key === 'gncilink-visitor-installed') {
            visitorInstalled=event.newValue === '1';
            if (visitorInstalled) {clearTimeout(promotionTimer);if(noticeKind==='install')closeNotice();if(installLink)installLink.hidden=true;}
        }
    });
    const installHost = admin ? document.body
        : document.querySelector('.gnc-footer-contact') || document.querySelector('.gnc-footer-legal') || document.querySelector('footer');
    if (installHost && !installed() && !authenticatedAdmin) {
        installLink = button('Install '+appName,install,'gnc-pwa-install-link');
        installLink.id = 'gncPwaInstall';
        if (admin) {
            installLink.classList.add('gnc-pwa-install-floating');
            installLink.title = 'Install '+appName; installLink.setAttribute('aria-label',installLink.title);
            const glyph = node('i',null,'bi bi-download'); glyph.setAttribute('aria-hidden','true');
            installLink.replaceChildren(glyph,node('span','Install web app'));
        } else {
            installLink.classList.add('gnc-pwa-install-footer');
            installLink.setAttribute('aria-label','Install '+appName);
            const glyph = node('i',null,'bi bi-download'); glyph.setAttribute('aria-hidden','true');
            installLink.replaceChildren(glyph,node('span','Install web app'));
        }
        installHost.append(installLink);
    }
    document.querySelector('[data-pwa-retry]')?.addEventListener('click',()=>location.reload());

    let strip;
    function connectivity() {
        if (admin) {
            if (!navigator.onLine) showNotice('offline','Admin panel is offline','Keep this page open. Saving, publishing and account actions need an internet connection. Changes are not sent automatically.','Got it',closeNotice);
            else if (noticeKind === 'offline') closeNotice();
            return;
        }
        if (!offlineCopy && navigator.onLine) {strip?.remove();strip=null;return;}
        if (!strip) {strip=node('div',null,'gnc-pwa-offline-strip');strip.setAttribute('role','status');document.body.prepend(strip);}
        strip.replaceChildren(node('span',navigator.onLine
            ? 'You’re viewing an offline copy. Refresh to check for the latest information.'
            : 'You’re offline. Saved information may be out of date. Chat, search and forms need a connection.'));
        if (navigator.onLine) strip.append(button('Refresh',()=>location.reload()));
    }
    connectivity();
    document.addEventListener('submit',event=>{
        if (navigator.onLine || event.target.method?.toLowerCase() !== 'post') return;
        event.preventDefault();
        showNotice('offline','An internet connection is needed','Your form has not been sent. Keep this page open and reconnect before submitting.','Got it',closeNotice);
    },true);
    window.addEventListener('offline',connectivity);
    window.addEventListener('online',()=>{
        connectivity();checkUpdate();
        // Fetch the fresh document into the public cache, keeping current form/reading state intact.
        if (offlineCopy) fetch(location.href,{cache:'no-store'}).catch(()=>{});
    });
    function checkUpdate() {
        if (!navigator.onLine || !registration || Date.now()-lastCheck<3600000) return;
        lastCheck=Date.now(); registration.update().catch(()=>{});
    }
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)checkUpdate();});
    if ('serviceWorker' in navigator) {
        const initiallyControlled = !!navigator.serviceWorker.controller;
        navigator.serviceWorker.register(admin ? '/admin/service-worker.js' : '/service-worker.js',{scope:admin ? '/admin/' : '/',updateViaCache:'none'}).then(async reg=>{
            // Let waiting workers activate normally after their old app tabs close.
            // Never display an update popup or force a refresh of work in progress.
            registration=reg;
            const ready = await navigator.serviceWorker.ready;
            if (!initiallyControlled && !admin) ready.active?.postMessage({type:'CACHE_CURRENT_PAGE'});
            checkUpdate();
        }).catch(()=>{}); // Website remains fully usable if storage or SWs are unavailable.
    }
    promote();
})();
