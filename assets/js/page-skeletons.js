(function () {
    'use strict';
    if (window.top !== window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    const loadedFrames = new WeakMap();
    document.addEventListener('load',event => {
        if (event.target.tagName==='IFRAME') loadedFrames.set(event.target,event.target.getAttribute('src'));
    },true);
    function start() {
        // Existing page-specific skeletons keep their own markup and lifecycle.
        if (document.querySelector('.skeleton-wrap,.skeleton-hero,.skeleton-group,.skeleton-line,script[src*="/skeleton-loader.js"]')) return;
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = '/assets/css/page-skeletons.css?v=1';
        document.head.appendChild(stylesheet);
        const active = new WeakMap(), backgrounds = new WeakSet(), parents = new WeakMap();
        const lazyWaiting = new WeakSet();
        const lazyObserver = typeof IntersectionObserver === 'function' ? new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                lazyObserver.unobserve(entry.target);lazyWaiting.delete(entry.target);image(entry.target,true);
            });
        },{rootMargin:'300px'}) : null;
        const excluded = 'nav,footer,dialog,.offcanvas,#gncPageEntrance,#gnc-intro,[data-ve-field]';
        function applicable(element) {
            return element.closest('main,section,article') && !element.closest(excluded)
                && !element.closest('.skeleton-wrap,.skeleton-hero,.skeleton-group');
        }
        function watch(element, className, finishExtra) {
            let done = false;
            const finish = () => {
                if (done) return;
                done = true; clearTimeout(timer);
                element.removeEventListener('load',finish);
                element.removeEventListener('error',finish);
                element.classList.remove(className);
                active.delete(element);
                if (finishExtra) finishExtra();
            };
            const timer = setTimeout(finish,15000);
            element.addEventListener('load',finish);
            element.addEventListener('error',finish);
            element.classList.add(className);
            active.set(element,finish);
            return finish;
        }
        function image(img,nearby=false) {
            if (active.has(img) || img.complete || !(img.currentSrc || img.getAttribute('src') || img.getAttribute('srcset'))) return;
            const bounds=img.getBoundingClientRect();
            if (!nearby && lazyObserver && img.loading==='lazy' && (bounds.top>innerHeight+300 || bounds.bottom< -300)) {
                if(!lazyWaiting.has(img)){lazyWaiting.add(img);lazyObserver.observe(img);}return;
            }
            const oldRatio = img.style.aspectRatio;
            const reserve = !img.getBoundingClientRect().height && getComputedStyle(img).aspectRatio === 'auto';
            if (reserve) img.style.aspectRatio = img.width && img.height ? img.width+'/'+img.height : '16 / 9';
            const finish = watch(img,'gnc-auto-skeleton-image',() => { if (reserve) img.style.aspectRatio=oldRatio; });
            // Handle a cached load completing between the first check and event binding.
            if (img.complete) finish();
        }
        function frame(iframe) {
            if (active.has(iframe) || iframe.hidden || !iframe.getAttribute('src') || iframe.getAttribute('src') === 'about:blank') return;
            if (loadedFrames.get(iframe)===iframe.getAttribute('src')) return;
            try {
                const expected = new URL(iframe.getAttribute('src'),document.baseURI);expected.hash='';
                if (iframe.contentDocument && iframe.contentDocument.readyState==='complete'
                    && iframe.contentDocument.URL.split('#')[0]===expected.href) return;
            } catch (_) {}
            const parent = iframe.parentElement;
            if (!parent) return;
            let state = parents.get(parent);
            if (!state) {
                state = {count:0,position:parent.style.position,changed:getComputedStyle(parent).position==='static'};
                parents.set(parent,state);
                if (state.changed) parent.style.position='relative';
            }
            state.count++;
            const overlay = document.createElement('div');
            overlay.className='gnc-auto-skeleton-frame';overlay.setAttribute('aria-hidden','true');
            parent.appendChild(overlay);
            function position() {
                overlay.style.left=iframe.offsetLeft+'px';overlay.style.top=iframe.offsetTop+'px';
                overlay.style.width=iframe.offsetWidth+'px';overlay.style.height=iframe.offsetHeight+'px';
            }
            position();
            const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(position) : null;
            if (resize) resize.observe(iframe);
            watch(iframe,'gnc-auto-skeleton-embed',() => {
                overlay.remove();if (resize) resize.disconnect();
                state.count--;
                if (!state.count) { if(state.changed)parent.style.position=state.position;parents.delete(parent); }
            });
        }
        function background(element) {
            if (backgrounds.has(element)) return;
            const value = getComputedStyle(element).backgroundImage;
            const urls = Array.from(value.matchAll(/url\(["']?([^"')]+)["']?\)/g),match => match[1]);
            if (!urls.length) return;
            backgrounds.add(element);
            const finish = watch(element,'gnc-auto-skeleton-background');
            Promise.all(urls.map(url => new Promise(resolve => {
                const preload = new Image();preload.onload=preload.onerror=resolve;preload.src=url;
                if (preload.complete) resolve();
            }))).then(finish);
        }
        function scan(root) {
            const selector = 'img,iframe,[class$="-hero"],[class*="-hero "],.hero-page';
            const elements = root.matches && root.matches(selector) ? [root] : [];
            elements.push(...root.querySelectorAll(selector));
            elements.forEach(element => {
                if (!applicable(element)) return;
                if (element.tagName==='IMG') image(element);
                else if (element.tagName==='IFRAME') frame(element);
                else background(element);
            });
        }
        scan(document);
        new MutationObserver(mutations => mutations.forEach(mutation => {
            if (mutation.type==='attributes') {
                const element=mutation.target;
                if (active.has(element)) active.get(element)();
                if (applicable(element)) { if(element.tagName==='IMG')image(element);else if(element.tagName==='IFRAME')frame(element); }
            } else mutation.addedNodes.forEach(node => { if(node.nodeType===1)scan(node); });
        })).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['src','srcset']});
    }
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',start,{once:true});
    else start();
})();
