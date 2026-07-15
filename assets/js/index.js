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

        function goTo(index) {
            const prev  = current;
            current     = (index + TOTAL) % TOTAL;
            if (current === prev) return;

            const leaving  = slides[prev];
            const entering = slides[current];

            const leavingVideo = videoIn(leaving);
            if (leavingVideo) leavingVideo.pause();

            leaving.classList.remove('active');
            entering.classList.add('active');

            if (dots.length) {
                dots.forEach(d => d.classList.remove('active'));
                if (dots[current]) dots[current].classList.add('active');
            }

            const enteringVideo = videoIn(entering);
            if (enteringVideo) {
                enteringVideo.currentTime = 0;
                enteringVideo.play().catch(() => {});
            }
        }

        function startAuto() {
            if (TOTAL <= 1) return; // nothing to rotate to
            clearInterval(autoTimer);
            autoTimer = setInterval(() => goTo(current + 1), INTERVAL);
        }

        function resetAuto() {
            startAuto();
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                goTo(current - 1);
                resetAuto();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                goTo(current + 1);
                resetAuto();
            });
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                const target = parseInt(dot.dataset.target, 10);
                if (target !== current) {
                    goTo(target);
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
                    dx < 0 ? goTo(current + 1) : goTo(current - 1);
                    resetAuto();
                }
            });
        }

        if (slides.length) {
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

document.addEventListener('DOMContentLoaded', function () {
    var lazyBgEls    = document.querySelectorAll('.lazy-bg[data-bg]');
    var lazyVideoEls = document.querySelectorAll('.lazy-video[data-src]');

    function loadBg(el) {
        if (!el.dataset.bg) return;
        el.style.backgroundImage = "url('" + el.dataset.bg + "')";
        el.classList.remove('lazy-bg');
        el.removeAttribute('data-bg');
    }

    function loadVideo(video) {
        if (video.dataset.src) {
            video.src = video.dataset.src;
            video.removeAttribute('data-src');
        }
        if (video.dataset.poster) {
            video.poster = video.dataset.poster;
            video.removeAttribute('data-poster');
        }
        video.preload = 'auto';
        video.load();
        video.classList.remove('lazy-video');
    }

    if ('IntersectionObserver' in window) {
        var bgObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    loadBg(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '200px 0px' });

        var videoObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    loadVideo(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '200px 0px' });

        lazyBgEls.forEach(function (el) { bgObserver.observe(el); });
        lazyVideoEls.forEach(function (video) { videoObserver.observe(video); });
    } else {
        // Older browsers without IntersectionObserver support: just load everything.
        lazyBgEls.forEach(loadBg);
        lazyVideoEls.forEach(loadVideo);
    }

    // The hero slideshow also needs the *next* slide ready just before
    // it's swiped/advanced to, so playback/transition doesn't stall.
    document.querySelectorAll('.hero-dots span, #slide-next, #slide-prev').forEach(function (control) {
        control.addEventListener('click', function () {
            document.querySelectorAll('.hero-slide').forEach(function (slideEl) {
                var bg = slideEl.querySelector('.lazy-bg[data-bg]');
                if (bg) loadBg(bg);
                var vid = slideEl.querySelector('.lazy-video[data-src]');
                if (vid) loadVideo(vid);
            });
        });
    });
});