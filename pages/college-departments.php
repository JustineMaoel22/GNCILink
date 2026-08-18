<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNC | College Departments</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/col-dept-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../components/index-nav.php'; ?>

    <section class="gnc-college-programs">

        <!-- Hero / Page Header -->
        <div class="cp-hero">
            <div class="cp-hero-inner">
                <nav class="cp-breadcrumb" aria-label="breadcrumb">
                    <a href="/">Home</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/academics">Academics</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="cp-current">College Departments</span>
                </nav>
                <h1>College Departments</h1>
                <p>Explore our colleges, and discover programs designed to shape your future.</p>
            </div>
        </div>

        <!-- Feature Highlights -->
        <div class="cp-features">
            <div class="cp-features-box">

                <div class="cp-feature">
                    <div class="cp-feature-icon">
                        <img src="/assets/images/svg/grad-hat-white.svg" alt="Quality Education">
                    </div>
                    <div class="cp-feature-text">
                        <h3>Quality Education</h3>
                        <p>Curriculum designed for real-world impact.</p>
                    </div>
                </div>

                <div class="cp-feature">
                    <div class="cp-feature-icon">
                        <img src="/assets/images/svg/person_play.svg" alt="Experienced Faculty">
                    </div>
                    <div class="cp-feature-text">
                        <h3>Experienced Faculty</h3>
                        <p>Learn from dedicated professionals.</p>
                    </div>
                </div>

                <div class="cp-feature">
                    <div class="cp-feature-icon">
                        <img src="/assets/images/svg/psychology-white.svg" alt="Holistic Development">
                    </div>
                    <div class="cp-feature-text">
                        <h3>Holistic Development</h3>
                        <p>Growing minds, character and leadership.</p>
                    </div>
                </div>

                <div class="cp-feature">
                    <div class="cp-feature-icon">
                        <img src="/assets/images/svg/real_estate_agent.svg" alt="Community Focused">
                    </div>
                    <div class="cp-feature-text">
                        <h3>Community Focused</h3>
                        <p>Committed to nation-building and service.</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Colleges Grid -->
        <div class="cp-colleges">
            <div class="cp-college-row">

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/cased-logo.svg" alt="College of Arts, Sciences, and Education logo">
                    <div class="cp-college-info">
                        <h3>College of Arts, Sciences, and Education</h3>
                        <span class="cp-underline"></span>
                        <p>The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.</p>
                        <a href="/pages/academics/departments/cased.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/cba-logo.svg" alt="College of Business Administration logo">
                    <div class="cp-college-info">
                        <h3>College of Business Administration</h3>
                        <span class="cp-underline"></span>
                        <p>The College aims to produce competent and well-rounded individuals who are prepared for the rigors of leadership and the demands of contemporary business and technology.</p>
                        <a href="/pages/academics/departments/cba.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/coe-logo.svg" alt="College of Engineering logo">
                    <div class="cp-college-info">
                        <h3>College of Engineering</h3>
                        <span class="cp-underline"></span>
                        <p>The College produces competent, ethical, community-oriented and globally competitive engineers who play a key role in the effective and efficient integrated design and construction of physical, technological and environmental systems.</p>
                        <a href="/pages/academics/departments/coe.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/camp-logo.svg" alt="College of Allied Medical Programs logo">
                    <div class="cp-college-info">
                        <h3>College of Allied Medical Programs</h3>
                        <span class="cp-underline"></span>
                        <p>The prepares future medical technologists and healthcare professionals through rigorous, research-based education, building the analytical and critical thinking skills needed for accurate diagnostics, medical innovation, and quality patient care.</p>
                        <a href="/pages/academics/departments/camp.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/coa-logo.svg" alt="College of Accountancy logo">
                    <div class="cp-college-info">
                        <h3>College of Accountancy</h3>
                        <span class="cp-underline"></span>
                        <p>The College aims to produce passers with topnotchers in the Certified Public Accountancy Board Examination and Accounting Information System Certification/Licensure Examination by providing proper training and materials.</p>
                        <a href="/pages/academics/departments/coa.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="cp-college">
                    <img class="cp-college-logo" src="/assets/images/logos/con-logo.svg" alt="College of Nursing logo">
                    <div class="cp-college-info">
                        <h3>College of Nursing</h3>
                        <span class="cp-underline"></span>
                        <p>The College prepares students to become globally competitive nurses grounded in current healthcare standards and best practices, and instills values of compassion and service.</p>
                        <a href="/pages/academics/departments/conursing.php" class="cp-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

            </div>
        </div>

    </section>

    <?php include __DIR__ . '/../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

</body>
</html>