/* Design coordinates inside the Content Editor preview only. */
(function () {
    'use strict';
    // Never scale a visitor page, even if it carries a preview query string.
    try {
        if (window.parent === window || window.frameElement?.id !== 'veFrame'
            || !window.parent.document.getElementById('veStage')) return;
    } catch (_) { return; }
    if (window.GNCResponsiveCanvas || !CSS.supports('zoom', '1')) return;
    const widths = {desktop:1920, tablet:820, mobile:390};
    const heights = {desktop:1080, tablet:1180, mobile:844};
    const root = document.documentElement, media = new WeakMap(), values = new WeakMap();
    // Native horizontal scrollbar thickness is measured in screen pixels and
    // would otherwise move the heading at different zoom levels. Scrolling,
    // touch swiping and keyboard navigation remain available.
    const style = document.createElement('style');
    style.textContent = 'html[data-gnc-canvas-device] .dept-breadcrumb{scrollbar-width:none}html[data-gnc-canvas-device] .dept-breadcrumb::-webkit-scrollbar{display:none}';
    document.head.appendChild(style);
    const originalRoot = ['width','zoom','overflow-x'].map(key => [key,root.style.getPropertyValue(key),root.style.getPropertyPriority(key)]);
    let forced = null, scale = 1, width = innerWidth, pending = 0, printing = false;
    const device = () => forced || (innerWidth < 768 ? 'mobile' : innerWidth <= 1024 ? 'tablet' : 'desktop');
    // Keep non-layout conditions (print, motion, hover, contrast) native.
    function query(source) {
        return source.replace(/\(\s*(min|max)-(width|height)\s*:\s*([\d.]+)(px|em|rem)\s*\)/gi, (_, kind, axis, n, unit) => {
            const threshold = Number(n) * (unit.toLowerCase() === 'px' ? 1 : 16);
            const dimension = axis.toLowerCase() === 'width' ? width : heights[device()];
            const matches = kind.toLowerCase() === 'min' ? dimension >= threshold : dimension <= threshold;
            return matches ? '(min-width: 0px)' : '(min-width: 10000000px)';
        }).replace(/\(\s*orientation\s*:\s*(portrait|landscape)\s*\)/gi, (_, orientation) =>
            (width > heights[device()] ? 'landscape' : 'portrait') === orientation.toLowerCase() ? '(min-width: 0px)' : '(min-width: 10000000px)');
    }
    function rules(list) {
        for (const rule of list) {
            if (rule.type === CSSRule.MEDIA_RULE) {
                if (!media.has(rule)) media.set(rule, rule.conditionText);
                const next = query(media.get(rule));
                if (rule.media.mediaText !== next) rule.media.mediaText = next;
            }
            if (rule.style) {
                let originals = values.get(rule);
                if (!originals) { originals = new Map(); values.set(rule, originals); }
                for (const property of rule.style) {
                    const value = rule.style.getPropertyValue(property);
                    if (/[\d.]+(?:[dsl]?v[wh]|vmin|vmax)\b/i.test(value) && !originals.has(property)) originals.set(property, value);
                }
                for (const [property, value] of originals) {
                    // Page content uses design dimensions. Viewport-bound UI
                    // remains constrained to the actual visible screen height.
                    const viewportUI = /dialog|modal|lightbox|offcanvas|gncbot|gncSiteDisclaimer|gnc-privacy|image-frame|dropdown|mega-menu|entrance|feedback/i.test(rule.selectorText || '')
                        || /chatbot|privacy|disclaimer|visitor-feedback|image-framing|page-entrance|admin-dialogs/.test(rule.parentStyleSheet?.href || '');
                    const height = viewportUI ? (window.visualViewport?.height || innerHeight) / scale : heights[device()];
                    const next = value.replace(/([\d.]+)([dsl]?v[wh]|vmin|vmax)\b/gi, (_, n, unit) => {
                        const dimension = unit.endsWith('w') ? width : unit === 'vmin' ? Math.min(width,height) : unit === 'vmax' ? Math.max(width,height) : height;
                        return Number(n) * dimension / 100 + 'px';
                    });
                    if (rule.style.getPropertyValue(property) !== next) rule.style.setProperty(property, next, rule.style.getPropertyPriority(property));
                }
            }
            if (rule.cssRules) rules(rule.cssRules);
            if (rule.styleSheet) { try { rules(rule.styleSheet.cssRules); } catch (_) { /* External fonts/icons. */ } }
        }
    }
    function apply() {
        cancelAnimationFrame(pending);
        if (printing) return;
        width = widths[device()];
        // The visual viewport excludes classic scrollbars. Multiplying by its
        // pinch scale keeps browser zoom available rather than cancelling it.
        const available = window.visualViewport ? visualViewport.width * visualViewport.scale : innerWidth;
        scale = available / width;
        root.style.width = width + 'px';
        root.style.zoom = String(scale);
        root.style.overflowX = 'clip';
        root.dataset.gncCanvasDevice = device();
        for (const sheet of document.styleSheets) {
            try { rules(sheet.cssRules); } catch (_) { /* Cross-origin font/icon styles. */ }
        }
    }
    function schedule() { cancelAnimationFrame(pending); pending = requestAnimationFrame(apply); }
    window.GNCResponsiveCanvas = {
        device, width:() => width, scale:() => scale,
        setDevice(value) { forced = Object.hasOwn(widths, value) ? value : null; apply(); },
        // Layout editing stores design pixels, never zoomed screen pixels.
        rect(element) {
            const r = element.getBoundingClientRect();
            return {left:r.left/scale, top:r.top/scale, right:r.right/scale, bottom:r.bottom/scale, width:r.width/scale, height:r.height/scale};
        }
    };
    apply();
    addEventListener('resize', apply);
    addEventListener('load', apply);
    window.visualViewport?.addEventListener('resize', schedule);
    // Printing retains the original stylesheet rather than a scaled screen.
    addEventListener('beforeprint', () => {
        printing = true;
        originalRoot.forEach(([key,value,priority]) => value ? root.style.setProperty(key,value,priority) : root.style.removeProperty(key));
        delete root.dataset.gncCanvasDevice;
        function restore(list) {
            for (const rule of list) {
                if (media.has(rule)) rule.media.mediaText = media.get(rule);
                if (values.has(rule)) for (const [key,value] of values.get(rule)) rule.style.setProperty(key,value,rule.style.getPropertyPriority(key));
                if (rule.cssRules) restore(rule.cssRules);
            }
        }
        for (const sheet of document.styleSheets) { try { restore(sheet.cssRules); } catch (_) {} }
    });
    addEventListener('afterprint', () => { printing = false; apply(); });
    document.addEventListener('load', event => { if (event.target.tagName === 'LINK') schedule(); }, true);
    new MutationObserver(schedule).observe(document.head, {childList:true, subtree:true, characterData:true});
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply, {once:true});
})();
