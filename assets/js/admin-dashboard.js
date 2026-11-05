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
})();
