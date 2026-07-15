<nav class="navbar gnc-navbar sticky-top navbar-expand-lg shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 me-3" href="/">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="GNC Logo" width="46" height="46">
            <div class="brand-text">
                <span class="brand-name d-block">Guagua National Colleges, Inc.</span>
                <span class="brand-tagline d-block">Fides, Scientia et. Patria</span>
            </div>
        </a>

        <!-- Mobile: opens offcanvas instead of collapse -->
        <button class="navbar-toggler border-0 ms-auto me-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mobileNav"
                aria-controls="mobileNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop nav (hidden below lg) -->
        <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">

                <li class="nav-item active">
                    <a class="nav-link active" href="/">Home</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">About</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="aboutDropdown">
                        <li><a class="dropdown-item" href="/about/history.php">History of GNC</a></li>
                        <li><a class="dropdown-item" href="/about/vision-mission.php">Vision and Mission</a></li>
                        <li><a class="dropdown-item" href="/about/core-values.php">Core Values</a></li>
                        <li><a class="dropdown-item" href="/about/logo-meaning.php">Institutional Logo and Meaning</a></li>
                        <li><a class="dropdown-item" href="/about/administration.php">Administration</a></li>
                        <li><a class="dropdown-item" href="/about/accreditations.php">Accreditations and Recognitions</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="academicsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Academics</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="academicsDropdown">
                        <li><a class="dropdown-item" href="/academics/basic-education.php">Basic Education</a></li>
                        <li><a class="dropdown-item" href="/academics/college.php">College</a></li>
                        <li><a class="dropdown-item" href="/academics/graduate-school.php">Graduate School</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="admissionsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Admissions</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="admissionsDropdown">
                        <li><a class="dropdown-item" href="/admissions/requirements.php">Admission Requirements</a></li>
                        <li><a class="dropdown-item" href="/admissions/enrollment-procedures.php">Enrollment Procedures</a></li>
                        <li><a class="dropdown-item" href="/admissions/tuition-fees.php">Tuition and Fees</a></li>
                        <li><a class="dropdown-item" href="/admissions/scholarship.php">Scholarship</a></li>
                        <li><a class="dropdown-item" href="/admissions/faqs.php">FAQs</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="studentLifeDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Student Life</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="studentLifeDropdown">
                        <li><a class="dropdown-item" href="/student-life/organizations.php">Student Organization</a></li>
                        <li><a class="dropdown-item" href="/student-life/leadership-programs.php">Student Leadership Programs</a></li>
                        <li><a class="dropdown-item" href="/student-life/campus-events.php">Campus Events</a></li>
                        <li><a class="dropdown-item" href="/student-life/community-extension.php">Community Extension Activities</a></li>
                        <li><a class="dropdown-item" href="/student-life/athletics-sports.php">Athletics and Sports</a></li>
                        <li><a class="dropdown-item" href="/student-life/achievements.php">Student Achievements</a></li>
                    </ul>
                </li>

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
                    <a class="btn-portal" href="/auth/login.php" target="_blank">
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

<!-- ================= MOBILE OFFCANVAS SIDEBAR ================= -->
<div class="offcanvas offcanvas-start gnc-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <span id="mobileNavLabel" class="visually-hidden">Main Menu</span>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <ul class="gnc-mobile-nav list-unstyled mb-0">

            <li class="gnc-mobile-item active">
                <a href="/" class="gnc-mobile-link">
                    <i class="bi bi-house-door-fill"></i>
                    <span>Home</span>
                </a>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAbout" aria-expanded="false">
                    <i class="bi bi-people-fill"></i>
                    <span>About</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu" id="mAbout">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/about/history.php">History of GNC</a></li>
                        <li><a href="/about/vision-mission.php">Vision and Mission</a></li>
                        <li><a href="/about/core-values.php">Core Values</a></li>
                        <li><a href="/about/logo-meaning.php">Institutional Logo and Meaning</a></li>
                        <li><a href="/about/administration.php">Administration</a></li>
                        <li><a href="/about/accreditations.php">Accreditations and Recognitions</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAcademics" aria-expanded="false">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>Academics</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu" id="mAcademics">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/academics/basic-education.php">Basic Education</a></li>
                        <li><a href="/academics/college.php">College</a></li>
                        <li><a href="/academics/graduate-school.php">Graduate School</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAdmissions" aria-expanded="false">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu" id="mAdmissions">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/admissions/requirements.php">Admission Requirements</a></li>
                        <li><a href="/admissions/enrollment-procedures.php">Enrollment Procedures</a></li>
                        <li><a href="/admissions/tuition-fees.php">Tuition and Fees</a></li>
                        <li><a href="/admissions/scholarship.php">Scholarship</a></li>
                        <li><a href="/admissions/faqs.php">FAQs</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mStudentLife" aria-expanded="false">
                    <i class="bi bi-people-fill"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu" id="mStudentLife">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/student-life/organizations.php">Student Organization</a></li>
                        <li><a href="/student-life/leadership-programs.php">Student Leadership Programs</a></li>
                        <li><a href="/student-life/campus-events.php">Campus Events</a></li>
                        <li><a href="/student-life/community-extension.php">Community Extension Activities</a></li>
                        <li><a href="/student-life/athletics-sports.php">Athletics and Sports</a></li>
                        <li><a href="/student-life/achievements.php">Student Achievements</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>News & Events</span>
                </a>
            </li>

            <li class="gnc-mobile-item">
                <a href="#" class="gnc-mobile-link">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Contact Us</span>
                </a>
            </li>
        </ul>

        <div class="gnc-mobile-search">
            <i class="bi bi-search"></i>
            <input type="search" class="gnc-mobile-search-input" placeholder="Search" aria-label="Search">
        </div>

        <div class="gnc-mobile-portal">
            <a class="btn-portal w-100 justify-content-center" href="/auth/login.php" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                </svg>
                Student Portal
            </a>
        </div>
    </div>
</div>