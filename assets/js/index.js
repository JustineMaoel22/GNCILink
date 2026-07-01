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

    /* ── Slideshow ── */
    const slides    = document.querySelectorAll('.hero-slide');
    const dots      = document.querySelectorAll('.hero-dots span');
    const prevBtn   = document.getElementById('slide-prev');
    const nextBtn   = document.getElementById('slide-next');
    const heroVideo = document.getElementById('hero-video');

    let current   = 0;
    let autoTimer = null;
    const INTERVAL = 10000;  // 10 seconds
    const TOTAL    = slides.length;

    function goTo(index, direction) {
        const prev  = current;
        current     = (index + TOTAL) % TOTAL;

        const leaving  = slides[prev];
        const entering = slides[current];

        if (prev === 1 && heroVideo) heroVideo.pause();

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

        dots.forEach(d => d.classList.remove('active'));
        dots[current].classList.add('active');

        if (current === 1 && heroVideo) {
            heroVideo.currentTime = 0;
            heroVideo.play().catch(() => {});
        }

        const content = entering.querySelector('.hero-content');
        if (content) {
            content.classList.remove('content-in');
            void content.offsetWidth;
            content.classList.add('content-in');
        }
    }

    function startAuto() {
        clearInterval(autoTimer);
        autoTimer = setInterval(() => goTo(current + 1, 'next'), INTERVAL);
    }

    function resetAuto() {
        startAuto();
    }

    prevBtn.addEventListener('click', function () {
        goTo(current - 1, 'prev');
        resetAuto();
    });
    nextBtn.addEventListener('click', function () {
        goTo(current + 1, 'next');
        resetAuto();
    });

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            const target = parseInt(dot.dataset.target, 10);
            if (target !== current) {
                goTo(target, target > current ? 'next' : 'prev');
                resetAuto();
            }
        });
    });

    let touchStartX = 0;
    const slideshow = document.querySelector('.hero-slideshow');
    slideshow.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
    slideshow.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchStartX;
        if (Math.abs(dx) > 50) {
            dx < 0 ? goTo(current + 1, 'next') : goTo(current - 1, 'prev');
            resetAuto();
        }
    });

    slides[0].querySelector('.hero-content').classList.add('content-in');
    startAuto();
});