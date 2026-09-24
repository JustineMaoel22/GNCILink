<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basic Education Departments</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/basic-edu-dept-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/index-nav.php'; ?>

    <section class="basic-edu-depts">

        <!-- Hero / Page Header -->
        <div class="basic-edu-hero">
            <div class="basic-edu-hero-inner">
                <nav class="basic-edu-breadcrumb" aria-label="breadcrumb">
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="basic-edu-current">Basic Education Departments</span>
                </nav>
                <h1>Basic Education Departments</h1>
                <p>Explore our programs designed to build strong foundations, develop character,and prepare learners for 
                    a bright future.</p>
            </div>
        </div>

        <!-- Feature Highlights -->
        <div class="basic-edu-features">
            <div class="basic-edu-features-box">

                <div class="basic-edu-feature">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/grad-hat-white.svg" alt="Quality Education">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3>Quality Education</h3>
                        <p>Curriculum designed for real-world impact.</p>
                    </div>
                </div>

                <div class="basic-edu-feature">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/person_play.svg" alt="Experienced Faculty">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3>Experienced Faculty</h3>
                        <p>Learn from dedicated professionals.</p>
                    </div>
                </div>

                <div class="basic-edu-feature">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/psychology-white.svg" alt="Holistic Development">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3>Holistic Development</h3>
                        <p>Growing minds, character and leadership.</p>
                    </div>
                </div>

                <div class="basic-edu-feature">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/real_estate_agent.svg" alt="Community Focused">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3>Community Focused</h3>
                        <p>Committed to nation-building and service.</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Basic Education Departments Grid -->
        <div class="basic-edu-colleges">
            <div class="basic-edu-college-row">

                <div class="basic-edu-college">
                    <div class="skeleton-wrap" style="flex:none;">
                        <img class="basic-edu-college-logo" src="/assets/images/logos/jhs-logo.svg" alt="College of Arts, Sciences, and Education logo">
                    </div>
                    <div class="basic-edu-college-info">
                        <h3>Junior High School Regular</h3>
                        <span class="basic-edu-underline"></span>
                        <p>The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive 
                            positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.</p>
                        <a href="/pages/academics/basic-edu/basic-edu-department/jhs-regular.php" class="basic-edu-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="basic-edu-college">
                    <div class="skeleton-wrap" style="flex:none;">
                        <img class="basic-edu-college-logo" src="/assets/images/logos/casa-monte-logo.svg" alt="College of Business Administration logo">
                    </div>
                    <div class="basic-edu-college-info">
                        <h3>CASA and Grade School Montessori</h3>
                        <span class="basic-edu-underline"></span>
                        <p>The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive 
                            positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.</p>
                        <a href="/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php" class="basic-edu-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="basic-edu-college">
                    <div class="skeleton-wrap" style="flex:none;">
                        <img class="basic-edu-college-logo" src="/assets/images/logos/shs-logo.svg" alt="College of Engineering logo">
                    </div>
                    <div class="basic-edu-college-info">
                        <h3>Senior High School Regular</h3>
                        <span class="basic-edu-underline"></span>
                        <p>The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive 
                            positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.</p>
                        <a href="/pages/academics/basic-edu/basic-edu-department/shs-regular.php" class="basic-edu-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="basic-edu-college">
                    <div class="skeleton-wrap" style="flex:none;">
                        <img class="basic-edu-college-logo" src="/assets/images/logos/hs-monte-logo.svg" alt="College of Allied Medical Programs logo">
                    </div>
                    <div class="basic-edu-college-info">
                        <h3>High School Montessori</h3>
                        <span class="basic-edu-underline"></span>
                        <p>The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive 
                            positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage..</p>
                        <a href="/pages/academics/basic-edu/basic-edu-department/hs-monte.php" class="basic-edu-view-link">View Programs <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

            </div>
        </div>

    </section>

    <?php include __DIR__ . '/../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

</body>
</html>