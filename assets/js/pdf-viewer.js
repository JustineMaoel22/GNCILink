/**
 * GNC PDF viewer — reads a PDF inside the page, styled like the browser's own
 * PDF viewer (dark toolbar; page n / total; editable zoom; fit; rotate;
 * download; print; menu; page thumbnails) but built on PDF.js so it looks and
 * behaves the same on desktop, tablet and phones (browsers' built-in viewers
 * show only page 1, or a bare download button, on many phones).
 *
 * Markup comes from components/pdf-viewer.php → renderPdfViewer().
 * PDF.js loads on demand from cdnjs, so pages without a PDF never fetch it.
 *
 * Pages scroll continuously and are drawn lazily: only pages near the screen
 * are rendered, the rest are freed, so long documents stay light on phones.
 */
(function () {
    'use strict';

    var PDFJS_VERSION = '3.11.174';
    var PDFJS_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/' + PDFJS_VERSION + '/';
    var U = 96 / 72;                                   // CSS px per PDF point at "100%"
    var PRESETS = [25, 33, 50, 67, 75, 80, 90, 100, 110, 125, 150, 175, 200, 250, 300, 400, 500];
    var MAX_CANVAS_PIXELS = 16 * 1024 * 1024;          // keeps phones from running out of memory
    var THUMB_W = 100;

    var libPromise = null;
    function loadPdfJs() {
        if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
        if (libPromise) return libPromise;
        libPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = PDFJS_BASE + 'pdf.min.js';
            s.async = true;
            s.onload = function () {
                if (!window.pdfjsLib) { reject(new Error('PDF.js failed to initialise')); return; }
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_BASE + 'pdf.worker.min.js';
                resolve(window.pdfjsLib);
            };
            s.onerror = function () { libPromise = null; reject(new Error('Could not load PDF.js')); };
            document.head.appendChild(s);
        });
        return libPromise;
    }

    function initViewer(root) {
        if (root.dataset.gncPdfReady) return;
        root.dataset.gncPdfReady = '1';

        var src = root.getAttribute('data-pdf-src');
        var q = function (role) { return root.querySelector('[data-role="' + role + '"]'); };
        var act = function (name) { return root.querySelector('[data-act="' + name + '"]'); };

        var stage = q('stage'), pagesEl = q('pages'), status = q('status');
        var sidebar = q('sidebar'), thumbsEl = q('thumbs');
        var pageInput = q('page-input'), pageCount = q('page-count'), zoomInput = q('zoom-input');
        var btnSidebar = act('sidebar'), btnZoomIn = act('zoom-in'), btnZoomOut = act('zoom-out');
        var btnFit = act('fit'), btnRotate = act('rotate'), btnPrint = act('print');
        var btnMore = act('more'), menu = q('menu'), btnRotateCw = act('rotate-cw');

        var pdfDoc = null, total = 0, cur = 1;
        var mode = 'auto';            // 'auto' (fit width, never above 100%) | 'width' | 'page' | 'custom'
        var zoom = 1;                 // used when mode === 'custom' (1 = 100%)
        var rot = 0;                  // user rotation, 0/90/180/270
        var S = 1;                    // current scale: CSS px per PDF point
        var sizes = [];               // sizes[i] = [w, h] in PDF points, before user rotation
        var wrappers = [], infos = [];
        var layoutKey = 0, lastWidth = 0, thumbsBuilt = false;

        /* ───────── helpers ───────── */
        function setStatus(msg, isError) {
            if (!msg) { status.hidden = true; return; }
            status.hidden = false;
            status.textContent = msg;
            status.classList.toggle('is-error', !!isError);
        }
        function dims(i) {
            var s = sizes[i] || sizes[1] || [612, 792];
            return (rot % 180) ? [s[1], s[0]] : [s[0], s[1]];
        }
        function padding() {
            var cs = window.getComputedStyle(stage);
            return {
                x: (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0),
                y: (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0)
            };
        }
        function pageTop(i) {
            return wrappers[i].getBoundingClientRect().top - stage.getBoundingClientRect().top + stage.scrollTop;
        }
        function computeScale() {
            var d = dims(cur), p = padding();
            var aw = Math.max(120, stage.clientWidth - p.x);
            var ah = Math.max(120, stage.clientHeight - p.y);
            if (mode === 'width') return aw / d[0];
            if (mode === 'page') return Math.min(aw / d[0], ah / d[1]);
            if (mode === 'custom') return zoom * U;
            return Math.min(aw / d[0], U);                       // auto
        }
        function percent() { return Math.round(S / U * 100); }

        /* ───────── controls state ───────── */
        function updateControls() {
            if (document.activeElement !== pageInput) pageInput.value = cur;
            pageCount.textContent = total || '–';
            pageInput.disabled = !pdfDoc;
            if (document.activeElement !== zoomInput) zoomInput.value = pdfDoc ? percent() + '%' : '100%';
            btnZoomOut.disabled = !pdfDoc || percent() <= PRESETS[0];
            btnZoomIn.disabled = !pdfDoc || percent() >= PRESETS[PRESETS.length - 1];
            [btnFit, btnRotate, btnPrint, btnMore].forEach(function (b) { if (b) b.disabled = !pdfDoc; });
            if (btnFit) {
                var label = mode === 'page' ? 'Fit to width' : 'Fit to page';
                btnFit.setAttribute('aria-label', label); btnFit.title = label;
                btnFit.classList.toggle('is-active', mode === 'page');
            }
            if (thumbsBuilt) {
                var thumbs = thumbsEl.children;
                for (var i = 0; i < thumbs.length; i++) thumbs[i].classList.toggle('is-current', i + 1 === cur);
                var t = thumbs[cur - 1];
                if (t && root.classList.contains('has-sidebar')) {
                    var r = t.getBoundingClientRect(), sr = sidebar.getBoundingClientRect();
                    if (r.top < sr.top || r.bottom > sr.bottom) sidebar.scrollTop += (r.top - sr.top) - (sr.height - r.height) / 2;
                }
            }
        }

        /* ───────── layout (sizes of the page slots) ───────── */
        function applyWrapperSize(i) {
            var d = dims(i);
            wrappers[i].style.width = Math.round(d[0] * S) + 'px';
            wrappers[i].style.height = Math.round(d[1] * S) + 'px';
        }
        function layout() {
            S = computeScale();
            layoutKey++;
            for (var i = 1; i <= total; i++) applyWrapperSize(i);
            lastWidth = stage.clientWidth;
            updateControls();
        }
        function anchor() {
            if (!wrappers[cur]) return null;
            return { page: cur, frac: (stage.scrollTop - pageTop(cur)) / (wrappers[cur].offsetHeight || 1) };
        }
        function restore(a) {
            if (!a || !wrappers[a.page]) return;
            stage.scrollTop = pageTop(a.page) + a.frac * wrappers[a.page].offsetHeight;
        }
        function relayout() {
            var a = anchor();
            layout();
            restore(a);
            detectCurrent();
            updateVisible();
        }

        /* ───────── rendering (lazy: only pages near the screen) ───────── */
        function unloadPage(i) {
            var st = infos[i], cv = wrappers[i].firstChild;
            if (st.task) { try { st.task.cancel(); } catch (e) {} st.task = null; }
            if (cv.width) { cv.width = 0; cv.height = 0; }
            st.rendered = null; st.rendering = null;
        }
        function renderPage(i) {
            var st = infos[i];
            if (st.rendered === layoutKey || st.rendering === layoutKey) return;
            if (st.task) { try { st.task.cancel(); } catch (e) {} st.task = null; }
            var key = layoutKey, scale = S, rotation = rot;
            st.rendering = key;

            pdfDoc.getPage(i).then(function (page) {
                if (key !== layoutKey) { st.rendering = null; return; }
                var natural = page.getViewport({ scale: 1 });
                if (!sizes[i] || Math.abs(sizes[i][0] - natural.width) > .5 || Math.abs(sizes[i][1] - natural.height) > .5) {
                    sizes[i] = [natural.width, natural.height];
                    applyWrapperSize(i);
                }
                var viewport = page.getViewport({ scale: scale, rotation: (page.rotate + rotation) % 360 });
                var cv = wrappers[i].firstChild, ctx = cv.getContext('2d');
                var ratio = Math.min(window.devicePixelRatio || 1, 2);
                var pixels = viewport.width * viewport.height * ratio * ratio;
                if (pixels > MAX_CANVAS_PIXELS) ratio = Math.sqrt(MAX_CANVAS_PIXELS / (viewport.width * viewport.height));
                cv.width = Math.floor(viewport.width * ratio);
                cv.height = Math.floor(viewport.height * ratio);
                st.task = page.render({
                    canvasContext: ctx,
                    viewport: viewport,
                    transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null
                });
                return st.task.promise.then(function () { if (key === layoutKey) st.rendered = key; });
            }).catch(function (err) {
                if (err && err.name === 'RenderingCancelledException') return;
                setStatus('Some pages could not be displayed. Try downloading the PDF instead.', true);
            }).then(function () { st.rendering = null; st.task = null; });
        }
        function updateVisible() {
            if (!pdfDoc) return;
            var h = stage.clientHeight, top = stage.scrollTop;
            var from = top - h, to = top + 2 * h;
            for (var i = 1; i <= total; i++) {
                var t = pageTop(i), b = t + wrappers[i].offsetHeight;
                if (b >= from && t <= to) renderPage(i); else if (infos[i].rendered || infos[i].rendering) unloadPage(i);
            }
        }
        function detectCurrent() {
            if (!pdfDoc) return;
            var mid = stage.scrollTop + stage.clientHeight * 0.35, n = 1;
            for (var i = 1; i <= total; i++) {
                if (pageTop(i) + wrappers[i].offsetHeight > mid) { n = i; break; }
                n = i;
            }
            if (n !== cur) { cur = n; updateControls(); }
        }

        var ticking = false;
        stage.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () { ticking = false; detectCurrent(); updateVisible(); });
        }, { passive: true });

        /* ───────── actions ───────── */
        function goTo(n) {
            if (!pdfDoc) return;
            n = Math.min(Math.max(1, n | 0), total);
            stage.scrollTop = Math.max(0, pageTop(n) - 8);
            cur = n; updateControls(); updateVisible();
        }
        function setCustomZoom(z) {
            var a = anchor();
            mode = 'custom'; zoom = Math.min(Math.max(z, PRESETS[0] / 100), PRESETS[PRESETS.length - 1] / 100);
            layout(); restore(a); detectCurrent(); updateVisible();
        }
        function stepZoom(dir) {
            var p = percent(), next = null, i;
            if (dir > 0) { for (i = 0; i < PRESETS.length; i++) if (PRESETS[i] > p) { next = PRESETS[i]; break; } }
            else { for (i = PRESETS.length - 1; i >= 0; i--) if (PRESETS[i] < p) { next = PRESETS[i]; break; } }
            if (next) setCustomZoom(next / 100);
        }
        function toggleFit() {
            var a = anchor();
            mode = (mode === 'page') ? 'width' : 'page';
            layout();
            if (mode === 'page') { restore({ page: cur, frac: 0 }); stage.scrollTop = Math.max(0, pageTop(cur) - 8); } else restore(a);
            detectCurrent(); updateVisible();
        }
        function rotate(delta) {
            var a = anchor();
            rot = (rot + delta + 360) % 360;
            layout(); restore(a); detectCurrent(); updateVisible();
        }
        function print() {
            var f = document.createElement('iframe');
            f.setAttribute('aria-hidden', 'true');
            f.style.cssText = 'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0;pointer-events:none';
            f.onload = function () {
                try { f.contentWindow.focus(); f.contentWindow.print(); }
                catch (e) { window.open(src, '_blank', 'noopener'); }
                setTimeout(function () { if (f.parentNode) f.parentNode.removeChild(f); }, 60000);
            };
            f.src = src;
            document.body.appendChild(f);
        }
        function closeMenu() { menu.hidden = true; btnMore.setAttribute('aria-expanded', 'false'); }

        /* ───────── thumbnails ───────── */
        function buildThumbs() {
            if (thumbsBuilt || !pdfDoc) return;
            thumbsBuilt = true;
            var s0 = sizes[1] || [612, 792], ratio = Math.min(window.devicePixelRatio || 1, 2);
            var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    var el = e.target; io.unobserve(el);
                    var i = +el.getAttribute('data-page');
                    pdfDoc.getPage(i).then(function (page) {
                        var nat = page.getViewport({ scale: 1 });
                        var vp = page.getViewport({ scale: THUMB_W / nat.width * ratio });
                        var cv = el.firstChild;
                        cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
                        cv.style.height = Math.round(THUMB_W * nat.height / nat.width) + 'px';
                        return page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
                    }).catch(function () {});
                });
            }, { root: sidebar, rootMargin: '300px 0px' }) : null;

            for (var i = 1; i <= total; i++) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'gnc-pdf-thumb'; b.setAttribute('data-page', i);
                b.setAttribute('aria-label', 'Go to page ' + i);
                var cv = document.createElement('canvas');
                cv.style.height = Math.round(THUMB_W * s0[1] / s0[0]) + 'px';
                var label = document.createElement('span'); label.textContent = i;
                b.appendChild(cv); b.appendChild(label);
                b.addEventListener('click', (function (n) { return function () { goTo(n); }; })(i));
                thumbsEl.appendChild(b);
                if (io) io.observe(b);
            }
            updateControls();
        }

        /* ───────── wiring ───────── */
        btnSidebar.addEventListener('click', function () {
            var open = root.classList.toggle('has-sidebar');
            btnSidebar.setAttribute('aria-pressed', open ? 'true' : 'false');
            if (open) buildThumbs();
        });
        btnZoomIn.addEventListener('click', function () { stepZoom(1); });
        btnZoomOut.addEventListener('click', function () { stepZoom(-1); });
        btnFit.addEventListener('click', toggleFit);
        btnRotate.addEventListener('click', function () { rotate(-90); });
        btnRotateCw.addEventListener('click', function () { rotate(90); closeMenu(); });
        btnPrint.addEventListener('click', print);
        btnMore.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.hidden;
            menu.hidden = !open;
            btnMore.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', function (e) { if (e.target.closest('a')) closeMenu(); });
        document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target)) closeMenu(); });
        root.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });

        pageInput.addEventListener('change', function () { goTo(parseInt(pageInput.value, 10) || cur); updateControls(); });
        pageInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); pageInput.blur(); } });
        pageInput.addEventListener('focus', function () { pageInput.select(); });
        zoomInput.addEventListener('change', function () {
            var v = parseFloat(String(zoomInput.value).replace('%', ''));
            if (isFinite(v) && v > 0) setCustomZoom(v / 100);
            zoomInput.blur(); updateControls();
        });
        zoomInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); zoomInput.blur(); } });
        zoomInput.addEventListener('focus', function () { zoomInput.select(); });

        stage.addEventListener('keydown', function (e) {
            if (e.key === '+' || e.key === '=') { e.preventDefault(); stepZoom(1); }
            else if (e.key === '-') { e.preventDefault(); stepZoom(-1); }
            else if (e.key === 'Home') { e.preventDefault(); goTo(1); }
            else if (e.key === 'End') { e.preventDefault(); goTo(total); }
            else if (stage.scrollWidth <= stage.clientWidth + 1) {          // only when there's nothing to pan sideways
                if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(cur - 1); }
                else if (e.key === 'ArrowRight') { e.preventDefault(); goTo(cur + 1); }
            }
        });

        // Re-fit when the container changes size (window resize, phone rotation, sidebar toggle).
        var resizeTimer = null;
        function onResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (pdfDoc && stage.clientWidth && Math.abs(stage.clientWidth - lastWidth) > 2) relayout();
            }, 120);
        }
        if (window.ResizeObserver) new ResizeObserver(onResize).observe(stage);
        else window.addEventListener('resize', onResize);

        /* ───────── load ───────── */
        updateControls();
        setStatus('Loading PDF…');

        loadPdfJs().then(function (pdfjsLib) {
            return pdfjsLib.getDocument({ url: src }).promise;
        }).then(function (doc) {
            pdfDoc = doc; total = doc.numPages;
            return doc.getPage(1).then(function (p) {
                var v = p.getViewport({ scale: 1 });
                sizes[1] = [v.width, v.height];
                for (var i = 1; i <= total; i++) {
                    var w = document.createElement('div');
                    w.className = 'gnc-pdf-page';
                    w.appendChild(document.createElement('canvas'));
                    pagesEl.appendChild(w);
                    wrappers[i] = w; infos[i] = { rendered: null, rendering: null, task: null };
                }
                root.classList.add('is-loaded');
                setStatus('');
                layout();
                updateVisible();
            });
        }).catch(function (err) {
            var msg = 'The PDF could not be displayed here.';
            if (err && err.name === 'PasswordException') msg = 'This PDF is password-protected and cannot be previewed.';
            else if (err && err.name === 'MissingPDFException') msg = 'This PDF is no longer available.';
            setStatus(msg + ' You can still use Download or Open in new tab.', true);
            root.classList.add('is-failed');
        });
    }

    function initAll() {
        var nodes = document.querySelectorAll('.gnc-pdf-viewer[data-pdf-src]');
        for (var i = 0; i < nodes.length; i++) initViewer(nodes[i]);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
    else initAll();
})();