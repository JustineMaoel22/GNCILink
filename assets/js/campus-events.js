(function () {
    'use strict';
    const section = document.getElementById('recent-events');
    const calendar = document.getElementById('events');
    if (!section || !calendar) return;
    const status = document.createElement('p');
    status.className = 'campus-recent-empty';
    status.setAttribute('role', 'status');
    section.appendChild(status);
    const calendarStatus = status.cloneNode(false);
    calendar.querySelector('.container').appendChild(calendarStatus);
    let pending;

    async function showPage(url, pushHistory, target = 'posts') {
        if (pending) pending.abort();
        const request = new AbortController();
        pending = request;
        section.setAttribute('aria-busy', 'true');
        calendar.setAttribute('aria-busy', 'true');
        status.textContent = '';
        calendarStatus.textContent = '';
        try {
            const response = await fetch(url, { signal: request.signal });
            if (!response.ok) throw new Error('Events request failed');
            const html = await response.text();
            if (request.signal.aborted) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const content = doc.querySelector('#recent-events > .container');
            const calendarContent = doc.getElementById('campus-calendar-content');
            const panel = doc.getElementById('calendarProgramPanel');
            if (!content || !calendarContent || !panel) throw new Error('Events content missing');
            section.querySelector('.container').replaceWith(content);
            document.getElementById('campus-calendar-content').replaceWith(calendarContent);
            // Keep the widget's toggle and listeners while updating its selected filter.
            document.getElementById('calendarProgramPanel').innerHTML = panel.innerHTML;
            document.dispatchEvent(new CustomEvent('calendar:updated'));
            if (pushHistory) {
                history.pushState({}, '', url);
                if (target === 'posts') {
                    section.scrollIntoView({ block: 'start', behavior: 'instant' });
                    const currentPage = section.querySelector('[aria-current="page"]');
                    if (currentPage) currentPage.focus({ preventScroll: true });
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                (target === 'calendar' ? calendarStatus : status).textContent = 'Unable to load events. Please try again.';
            }
        } finally {
            if (pending === request) {
                pending = null;
                section.removeAttribute('aria-busy');
                calendar.removeAttribute('aria-busy');
            }
        }
    }

    section.addEventListener('click', event => {
        const link = event.target.closest('.campus-recent-pagination a');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (link.getAttribute('aria-current') === 'page') return;
        showPage(link.href, true);
    });
    calendar.addEventListener('click', event => {
        const link = event.target.closest('a.gnc-cal-nav,.gnc-programs-list-item,.gnc-departments-list-item,.gnc-calendar-filter-reset,.gnc-evs-head a.gnc-programs-count');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        showPage(link.href, true, 'calendar');
    });
    window.addEventListener('popstate', () => showPage(location.href, false));
})();
