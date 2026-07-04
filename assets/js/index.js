document.addEventListener('DOMContentLoaded', function () {

    /* ── Search toggle ── */
    const toggle        = document.getElementById('search-toggle');
    const searchBoxItem = document.getElementById('search-box-item');
    const searchInput   = document.getElementById('search-input');

    if (toggle && searchBoxItem && searchInput) {
        toggle.addEventListener('click', function () {
            const isVisible = searchBoxItem.style.display === 'flex';
            searchBoxItem.style.display = isVisible ? 'none' : 'flex';
            if (!isVisible) searchInput.focus();
        });
        document.addEventListener('click', function (e) {
            if (!toggle.contains(e.target) && !searchBoxItem.contains(e.target)) {
                searchBoxItem.style.display = 'none';
            }
        });
    }

    /* ── Slideshow (supports any number of admin-managed slides) ── */
    const slideshow = document.querySelector('.hero-slideshow');

    if (slideshow) {
        const slides  = document.querySelectorAll('.hero-slide');
        const dots    = document.querySelectorAll('.hero-dots span');
        const prevBtn = document.getElementById('slide-prev');
        const nextBtn = document.getElementById('slide-next');

        let current   = 0;
        let autoTimer = null;
        const INTERVAL = 10000;  // 10 seconds
        const TOTAL    = slides.length;

        // Any slide may contain its own <video>; look it up per-slide rather
        // than assuming a single fixed hero video.
        function videoIn(slideEl) {
            return slideEl.querySelector('.hero-slide-video');
        }

        function goTo(index, direction) {
            const prev  = current;
            current     = (index + TOTAL) % TOTAL;
            if (current === prev) return;

            const leaving  = slides[prev];
            const entering = slides[current];

            const leavingVideo = videoIn(leaving);
            if (leavingVideo) leavingVideo.pause();

            leaving.classList.add(direction === 'prev' ? 'exit-right' : 'exit-left');
            leaving.classList.remove('active');

            entering.classList.add(direction === 'prev' ? 'enter-from-left' : 'enter-from-right');

            entering.getBoundingClientRect();

            entering.classList.add('active');
            entering.classList.remove('enter-from-left', 'enter-from-right');

            leaving.addEventListener('transitionend', function cleanup() {
                leaving.classList.remove('exit-left', 'exit-right');
                leaving.removeEventListener('transitionend', cleanup);
            });

            if (dots.length) {
                dots.forEach(d => d.classList.remove('active'));
                if (dots[current]) dots[current].classList.add('active');
            }

            const enteringVideo = videoIn(entering);
            if (enteringVideo) {
                enteringVideo.currentTime = 0;
                enteringVideo.play().catch(() => {});
            }

            const content = entering.querySelector('.hero-content');
            if (content) {
                content.classList.remove('content-in');
                void content.offsetWidth;
                content.classList.add('content-in');
            }
        }

        function startAuto() {
            if (TOTAL <= 1) return; // nothing to rotate to
            clearInterval(autoTimer);
            autoTimer = setInterval(() => goTo(current + 1, 'next'), INTERVAL);
        }

        function resetAuto() {
            startAuto();
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                goTo(current - 1, 'prev');
                resetAuto();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                goTo(current + 1, 'next');
                resetAuto();
            });
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                const target = parseInt(dot.dataset.target, 10);
                if (target !== current) {
                    goTo(target, target > current ? 'next' : 'prev');
                    resetAuto();
                }
            });
        });

        if (TOTAL > 1) {
            let touchStartX = 0;
            slideshow.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
            slideshow.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - touchStartX;
                if (Math.abs(dx) > 50) {
                    dx < 0 ? goTo(current + 1, 'next') : goTo(current - 1, 'prev');
                    resetAuto();
                }
            });
        }

        if (slides.length) {
            const firstContent = slides[0].querySelector('.hero-content');
            if (firstContent) firstContent.classList.add('content-in');

            const firstVideo = videoIn(slides[0]);
            if (firstVideo) firstVideo.play().catch(() => {});
        }

        startAuto();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.body.addEventListener('click', async (e) => {
        const navLink = e.target.closest('.gnc-cal-nav');
        if (!navLink) return;

        e.preventDefault(); 
        
        const url = navLink.href;

        try {
            const response = await fetch(url);
            const html = await response.text();

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newCalendar = doc.querySelector('.gnc-cal-card').innerHTML;

            document.querySelector('.gnc-cal-card').innerHTML = newCalendar;
            
            window.history.pushState({}, '', url);
        } catch (error) {
            console.error('Failed to load calendar:', error);
            window.location.href = url;
        }
    });
});