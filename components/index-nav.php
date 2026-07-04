<nav class="navbar gnc-navbar sticky-top navbar-expand-lg shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 me-3">
            <img src="assets/images/logos/gnc-logo-v1.svg" alt="GNC Logo" width="46" height="46">
            <div class="brand-text">
                <span class="brand-name d-block">Guagua National Colleges, Inc.</span>
                <span class="brand-tagline d-block">Fides, Scientia et. Patria</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 ms-auto me-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item active">
                    <a class="nav-link active" href="#">Home</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="#">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Academics</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Admissions</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Student Life</a></li>
                <li class="nav-item"><a class="nav-link" href="#">News & Events</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Contact Us</a></li>

                <li class="nav-item d-flex align-items-center">
                    <button id="search-toggle" class="btn-search" type="button" aria-label="Search">
                        <i class="bi bi-search"></i>
                    </button>
                </li>
                <li class="nav-item" id="search-box-item" style="display:none;">
                    <form class="d-flex" action="#" onsubmit="return false;">
                        <input id="search-input" class="form-control form-control-sm"
                            type="search" placeholder="Search…" aria-label="Search"
                            style="min-width:160px;">
                    </form>
                </li>

                <li class="nav-item ms-2">
                    <a class="btn-portal" href="auth/login.php" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                        </svg>
                        Student Portal
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>