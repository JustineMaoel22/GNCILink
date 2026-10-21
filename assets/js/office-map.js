(function () {
    'use strict';
    var modal = document.getElementById('officeMapModal');
    if (!modal) return;
    var frame = modal.querySelector('iframe'), help = modal.querySelector('[role="status"]');
    var trigger = null, location = null, closing = null, loading = null;
    var pageScroll = null;
    function highlight() {
        if (!location) return;
        var map = frame.contentWindow;
        if (!map || !map.B || typeof map.go !== 'function') return;
        clearTimeout(loading);
        if (location.building_id && Object.prototype.hasOwnProperty.call(map.B, location.building_id)) {
            map.go(location.building_id);
            help.textContent = location.building + ' is highlighted. Zoom or drag to explore the map.';
        } else {
            map.clear(true);
            help.textContent = 'A mapped building has not been assigned to this office. Please use the location details above.';
        }
    }
    function close() {
        if (!modal.open || closing) return;
        modal.classList.remove('is-open');
        closing = setTimeout(function () { modal.close(); }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180);
    }
    frame.addEventListener('load', function () {
        try {
            highlight();
            // Escape also works while focus is inside the map's iframe.
            frame.contentDocument.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); close(); }
            });
        } catch (error) { help.textContent = 'The map could not load. The office location is listed above.'; }
    });
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-office-location]');
        if (!button) return;
        try { location = JSON.parse(button.dataset.officeLocation); } catch (error) { return; }
        if (closing) { clearTimeout(closing); closing = null; }
        trigger = button;
        document.getElementById('officeMapTitle').textContent = location.name;
        document.getElementById('officeMapAddress').textContent = location.address || 'Office location has not been provided.';
        help.textContent = 'Loading campus map…';
        pageScroll = { left: window.scrollX, top: window.scrollY, behavior: 'instant' };
        document.documentElement.classList.add('office-map-open');
        modal.showModal();
        window.scrollTo(pageScroll);
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        if (!frame.getAttribute('src')) {
            loading = setTimeout(function () { help.textContent = 'The map is taking longer to load. The office location is listed above.'; }, 12000);
            frame.src = '/campus_navigator.html?view=office';
        } else highlight();
    });
    modal.querySelector('.office-map-close').addEventListener('click', close);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.open) { event.preventDefault(); close(); }
    });
    modal.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
    modal.addEventListener('click', function (event) {
        if (event.target !== modal) return;
        var rect = modal.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
    });
    modal.addEventListener('close', function () {
        clearTimeout(closing); closing = null;
        modal.classList.remove('is-open');
        document.documentElement.classList.remove('office-map-open');
        if (pageScroll) window.scrollTo(pageScroll);
        if (trigger) trigger.focus({ preventScroll: true });
    });
})();
