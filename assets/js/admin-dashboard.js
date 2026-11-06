(() => {
    'use strict';
    const time = document.getElementById('dashClockTime');
    const date = document.getElementById('dashDate');
    const greeting = document.getElementById('dashGreeting');
    const zone = 'Asia/Manila';
    const timeFormat = new Intl.DateTimeFormat('en-US', {timeZone: zone, hour: 'numeric', minute: '2-digit', hour12: true});
    const dateFormat = new Intl.DateTimeFormat('en-US', {timeZone: zone, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
    const hourFormat = new Intl.DateTimeFormat('en-US', {timeZone: zone, hour: 'numeric', hourCycle: 'h23'});
    function update() {
        if (document.hidden) return;
        const now = new Date();
        const hour = Number(hourFormat.format(now));
        time.textContent = timeFormat.format(now);
        date.textContent = dateFormat.format(now);
        greeting.textContent = hour >= 5 && hour < 12 ? 'Good morning' : hour >= 12 && hour < 18 ? 'Good afternoon' : 'Good evening';
    }
    update();
    setInterval(update, 30000);
    document.addEventListener('visibilitychange', update);

    const totals = document.querySelector('[data-daily-totals]');
    if (totals) {
        const rows = [...totals.querySelectorAll('tbody tr')];
        const pageSize = 7, pages = Math.ceil(rows.length / pageSize);
        const pagination = totals.querySelector('[data-daily-pagination]');
        const controls = totals.querySelector('[data-daily-controls]');
        const status = totals.querySelector('[data-daily-status]');
        if (pages > 1) {
            let page = 1;
            function render() {
                const start = (page - 1) * pageSize;
                rows.forEach((row, index) => { row.hidden = index < start || index >= start + pageSize; });
                status.textContent = `Showing ${start + 1}–${Math.min(start + pageSize, rows.length)} of ${rows.length} days · Page ${page} of ${pages}`;
                controls.replaceChildren();
                function button(label, target, disabled, current) {
                    const node = document.createElement('button');
                    node.type = 'button'; node.textContent = label; node.disabled = disabled;
                    node.setAttribute('aria-controls', 'dashboard-daily-table');
                    if (current) node.setAttribute('aria-current', 'page');
                    if (/^\d+$/.test(label)) node.setAttribute('aria-label', `Page ${label}`);
                    node.addEventListener('click', () => {
                        page = target; render();
                        controls.querySelector('[aria-current="page"]').focus({preventScroll:true});
                    });
                    controls.appendChild(node);
                }
                button('Previous', page - 1, page === 1, false);
                for (let number = 1; number <= pages; number++) button(String(number), number, false, number === page);
                button('Next', page + 1, page === pages, false);
            }
            render(); pagination.hidden = false;
        }
    }
})();
