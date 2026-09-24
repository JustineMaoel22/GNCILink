<?php
/**
 * Administration page — single file, section switched via ?section=
 * Sections: board-of-trustees | corporate-officers | board-of-administration | heads-of-student-services
 */

$section = $_GET['section'] ?? 'board-of-trustees';

$sections = [

    'board-of-trustees' => [
        'title' => 'Board of Trustees',
        'desc'  => 'The governing body responsible for setting the direction, policies, and overall stewardship of the institution.',
        'people' => [
            ['img' => 'chairmain-goseco.png',                 'name' => 'Ronald Luis S. Goseco, MBA',            'role' => 'Chairman'],
            ['img' => 'vice-chairman-lim.png',                'name' => 'Geraldine G. Lim, MBA',                 'role' => 'Vice Chairman'],
            ['img' => 'trustee-sampang.png',                  'name' => 'Atty. Ricardo M. Sampang',              'role' => 'Trustee'],
            ['img' => 'idependent-trustee-beltran.png',       'name' => 'Ms. Estrella A. Beltran',               'role' => 'Independent Trustee'],
            ['img' => 'trustee-limlingan.png',                'name' => 'Dr. Maria Christina A. Limlingan',      'role' => 'Trustee'],
            ['img' => 'trustee-puno.png',                     'name' => 'Engr. Jose Manuel L. Puno',             'role' => 'Trustee'],
            ['img' => 'trustee-nicdao.png',                   'name' => 'Engr. Jesus S. Nicdao',                 'role' => 'Trustee'],
            ['img' => 'trustee-salvador.png',                 'name' => 'Ana Maria Margarita S. Salvador, Ph.D.','role' => 'Trustee'],
            ['img' => 'independent-trustee-baluyut.png',      'name' => 'Arch. Karina M. Baluyut',               'role' => 'Independent Trustee'],
            ['img' => 'independent-trustee-carlos.png',       'name' => 'Ms. Maria Ruby C. Carlos',              'role' => 'Independent Trustee'],
            ['img' => 'independent-trustee-dela-fuente.png',  'name' => 'Mary Clarence M. Dela Fuente, MBA',     'role' => 'Independent Trustee'],
        ],
    ],

    'corporate-officers' => [
        'title' => 'Corporate Officers',
        'desc'  => 'The corporate leadership responsible for guiding the institution\'s governance, financial stewardship, and overall organizational direction.',
        'people' => [
            ['img' => 'chairmain-goseco.png',         'name' => 'Ronald Luis S. Goseco, MBA', 'role' => 'Chairman'],
            ['img' => 'vice-chairman-lim.png',        'name' => 'Geraldine G. Lim, MBA',      'role' => 'Vice Chairman'],
            ['img' => 'trustee-sampang.png',          'name' => 'Atty. Ricardo M. Sampang',   'role' => 'Corporate Secretary'],
            ['img' => 'corporate-treasurer-isip.png', 'name' => 'Getor N. Isip, CPA, MBA',    'role' => 'Corporate Treasurer'],
        ],
    ],

    'board-of-administration' => [
        'title' => 'Board of Administration',
        'desc'  => 'The administrators who oversee the day-to-day academic and operational management of the institution.',
        'people' => [
            ['img' => 'vice-chairman-lim.png',        'name' => 'Geraldine G. Lim, MBA',             'role' => 'President'],
            ['img' => 'hic-nulud.png',                'name' => 'Allen Jan S. Nulud, Ph.D.',         'role' => 'OIC, Principal, Junior High School Regular Program and OIC, CASA & Grade School Montessori'],
            ['img' => 'dean-gradschool-nazal.png',    'name' => 'Lolita D. Nazal, Ed.D.',            'role' => 'Dean, Graduate School'],
            ['img' => 'oic-sacdalan.png',             'name' => 'Engr. Ma. Lydia P. Sacdalan',       'role' => 'OIC, College of Engineering'],
            ['img' => 'corporate-treasurer-isip.png', 'name' => 'Getor N. Isip, CPA, MBA',           'role' => 'Dean, College of Accountancy'],
            ['img' => 'maam-bernardo.png',            'name' => 'Wilhelmina G. Bernardo, RN, RM, MAN','role' => 'Dean, College of Nursing'],
            ['img' => 'maam-maris.png',               'name' => 'Annaliza V. Maris, MA, LPT',        'role' => 'Dean, College of Arts, Sciences, and Education'],
            ['img' => 'maam-sampang.png',             'name' => 'Rowena R. Sampang, MBA',            'role' => 'Dean, College of Business Administration'],
            ['img' => 'sir-capati.png',               'name' => 'Juan Paolo S. Capati, RMT, RPh',    'role' => 'Dean, College of Medical Technology and Pharmacy'],
            ['img' => 'oic-cayanan.png',              'name' => 'Rhoda SR. Cayanan, RPh, LPT',       'role' => 'OIC, Senior High School Department'],
            ['img' => 'principal-dela-cruz.png',      'name' => 'Mary Jane C. Dela Cruz, LPT, MA',   'role' => 'Principal, Junior and Senior High School Montessori'],
        ],
    ],

    'heads-of-student-services' => [
        'title' => 'Heads of Student Services',
        'desc'  => 'The offices responsible for delivering essential administrative, academic, and student support services that promote a safe, organized, and supportive learning environment.',
        'offices' => [
            [
                'img'   => 'business-office-head.png',
                'name'  => 'Getor N. Isip, MBA, CPA',
                'role'  => 'School Accountant, Chief Financial Officer',
                'office' => 'Business Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 107',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'BETA Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 8:00AM - 4:00PM<br>Saturday | 8:00AM - 3:00PM',
                ],
                'services' => [
                    'General Payment Transactions',
                    'Scholarship and Financial Assistance Processing',
                    'Student Loan Assistance',
                    'Online Payment Processing',
                    'Disbursement and Refund Services',
                    'Payroll Services',
                    'Financial and Administrative Liaison Services',
                ],
            ],
            [
                'img'   => 'registrar-office-head.png',
                'name'  => 'Julie B. Domingo, MBA',
                'role'  => 'Chief Registrar',
                'office' => 'Registrar Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 106',
                    'email'    => 'registrar@gnc.edu.ph',
                    'location' => 'BETA Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM<br>Saturday | 8:00AM - 3:00PM',
                ],
                'services' => [
                    'Enrollment Services',
                    'Student Records Services',
                    'Academic Records and Verification',
                    'Certification and Document Services',
                    'Transfer and Clearance Services',
                    'Records Request and Processing',
                    'Student Information Assistance',
                ],
            ],
            [
                'img'   => 'osa-office-head.png',
                'name'  => 'Jewel Mae E. Samera, RPm',
                'role'  => 'Directress',
                'office' => 'Office of Student Affairs',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 109',
                    'email'    => 'osa@gnc.edu.ph', // TODO: confirm — hard to read in screenshot
                    'location' => 'Limlingan Hall | Floor 2 | Room 100',
                    'hours'    => 'Monday - Friday | 8:00AM - 4:00PM',
                ],
                'services' => [
                    // TODO: replace lorem ipsum with actual OSA services
                ],
            ],
            [
                'img'   => 'college-library-head.png',
                'name'  => 'Edna C. Valencia, MLIS',
                'role'  => 'Chief Librarian',
                'office' => 'College Library',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 113',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Puno Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Saturday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'Library Resource Access',
                    'Book Borrowing and Return Services',
                    'Internet and Research Assistance',
                    'Reader Assistance',
                    'Referral Services',
                    'Library Orientation',
                    'External Researcher Assistance',
                ],
            ],
            [
                'img'   => 'high-school-library-head.png',
                'name'  => 'Jhelou A. Supan, MLIS, ITS, ICCC, MOS',
                'role'  => 'OIC, High School Library',
                'office' => 'High School Library',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 116',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Goseco Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 7:00AM - 5:30PM',
                ],
                'services' => [
                    'Library Access and Circulation',
                    'Research and Reference Assistance',
                    'Computer and Internet Services',
                    'Library Orientation and User Registration',
                    'Referral and Information Services',
                    'Library Collection Management',
                    'Library Resources and Preservation',
                    'Librarian Assistance',
                ],
            ],
            [
                'img'   => 'elementary-library-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Marivic Lansang, MLIS',
                'role'  => 'OIC, Elementary Library',
                'office' => 'Elementary Library',
                'contact' => [
                    'phone'    => '(045) 900-4473',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Banzali Hall | Floor 1 | Room 100',
                    'hours'    => 'Monday - Friday | 7:00AM - 4:00PM',
                ],
                'services' => [
                    'Book and Learning Resource Access',
                    'Book Borrowing Services',
                    'Library Assistance',
                    'Learner Reading Support',
                ],
            ],
            [
                'img'   => 'guidance-office-head.png',
                'name'  => 'Patria Pilipina D. Capa, MA, RGC, RPm',
                'role'  => 'Chief Guidance Counselor',
                'office' => 'Guidance Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 108',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Goseco Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'Admission and Orientation Services',
                    'Student Assessment Services',
                    'Counseling and Personal Support',
                    'Information and Referral Services',
                    'Career Guidance Services',
                    'Seminars and Development Programs',
                    'Peer Support and Leadership Programs',
                    'Guidance Program Evaluation',
                ],
            ],
            [
                'img'   => 'clinic-head.png',
                'name'  => 'Karen G. Bernardo, MD, RN, RM, MN, MPHA',
                'role'  => 'School Physician',
                'office' => 'Clinic',
                'contact' => [
                    'phone'    => '(045) 900-4473',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'BETA Hall | Floor 1 | Room 100',
                    'hours'    => 'Monday - Friday | 6:30AM - 8:00PM<br>Saturday | 7:00AM - 8:00PM',
                ],
                'services' => [
                    'Student Health Consultation',
                    'Health Assessment and Diagnosis',
                    'Medical Referral Services',
                    'Sports and Medical Examination',
                    'Medication and Treatment Assistance',
                ],
            ],
            [
                'img'   => 'purchasing-office-head.png',
                'name'  => 'Richard B. Sicat',
                'role'  => 'OIC, Purchasing Office',
                'office' => 'Purchasing Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 123',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Guardhouse | Floor 1 | Room 100',
                    'hours'    => 'Monday - Saturday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'School Supplies and Essentials',
                    'Uniform and PE Uniform Sales',
                    'Books and Learning Materials',
                    'School Identification Accessories',
                    'Other School-Related Supplies',
                ],
            ],
            [
                'img'   => 'security-office-head.png',
                'name'  => 'Johnny C. Garcia',
                'role'  => 'Security-In-Charge',
                'office' => 'Security Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 122',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Puno Hall | Floor 1 | Room 100',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'Campus Security Services',
                    'Safety and Welfare Protection',
                    'Property and Asset Protection',
                    'Visitor and Access Control',
                    'Parking Sticker Issuance',
                ],
            ],
            [
                'img'   => 'mis-office-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Michael P. Maglanque, MBA',
                'role'  => 'Network Administrator',
                'office' => 'MIS Office',
                'contact' => [
                    'phone'    => '(045) 900-4473',
                    'email'    => 'mis@gnc.edu.ph',
                    'location' => 'Limlingan Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'ID Printing Services',
                    'Yearbook Production',
                    'Computer and Technical Services',
                    'IT and Digital Support',
                    'Other Computer-Related Services',
                ],
            ],
            [
                'img'   => 'computer-technician-office-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Harry P. Samonte',
                'role'  => 'Head Technician',
                'office' => 'Computer Technician Office',
                'contact' => [
                    'phone'    => '(045) 900-4473 Loc. 124',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Puno Hall | Floor 4 | Room 401',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    // TODO: replace lorem ipsum with actual Computer Technician Office services
                ],
            ],
            [
                'img'   => 'centralized-laboratory-stockroom-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Ricardo Antonio L. Libuna',
                'role'  => 'Laboratory Technician',
                'office' => 'Centralized Laboratory Stockroom',
                'contact' => [
                    'phone'    => '(045) 900-4473',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Limlingan Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Friday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'Laboratory Supplies and Equipment Management',
                    'Laboratory Materials Distribution',
                    'Stockroom Inventory Management',
                    'Laboratory Equipment Maintenance',
                    'Departmental Laboratory Support',
                ],
            ],
            [
                'img'   => 'printing-nstp-office-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Evangeline Gagui',
                'role'  => 'Head, Printing Office and NSTP Office',
                'office' => 'Printing Office and NSTP Office',
                'contact' => [
                    'phone'    => '09485239722',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Goseco Hall | Floor 2 | Room 200',
                    'hours'    => 'Monday - Saturday | 8:00AM - 5:00PM',
                ],
                'services' => [
                    'Printing and Reproduction Services',
                    'Binding and Document Production',
                    'Instructional and Academic Material Production',
                    'Forms, Reports, and Institutional Document Printing',
                    'NSTP Program Management',
                    'Student Development and Support',
                    'Community Engagement and Service',
                    'Leadership and Civic Development',
                ],
            ],
            [
                'img'   => 'sports-office-head.png', // TODO: no photo shown in screenshot yet — placeholder filename
                'name'  => 'Eduardo V. Gozun Jr.',
                'role'  => 'GNCI Sports Coordinator',
                'office' => 'Sports Office',
                'contact' => [
                    'phone'    => '(045) 900-4473',
                    'email'    => 'info@gnc.edu.ph',
                    'location' => 'Tanjangco Hall | Floor 1 | Room 100',
                    'hours'    => 'Monday - Saturday | 9:00AM - 5:00PM',
                ],
                'services' => [
                    'Sports Training and Development',
                    'Athlete Support and Development',
                    'Sports Program Management',
                    'Competition Preparation',
                ],
            ],
        ],
    ],

];

if (!isset($sections[$section])) {
    $section = 'board-of-trustees';
}

$current = $sections[$section];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/administration-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="admin-intro">
        <div class="admin-hero">
            <div class="admin-hero-inner">
                <nav class="admin-breadcrumb" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="admin-current">Administration</span>
                </nav>
                <h1>Administration</h1>
                <p>Meet the leaders who guide our institution with vision, dedication, and service.</p>
            </div>
        </div>
    </section>

    <!-- Sub-navigation Tabs -->
    <section class="admin-tabs-wrap">
        <div class="admin-tabs-inner">
            <nav class="admin-tabs" aria-label="Administration sections">
                <a href="?section=board-of-trustees"
                   class="admin-tab <?= $section === 'board-of-trustees' ? 'active' : ''; ?>">
                    Board of Trustees
                </a>
                <a href="?section=corporate-officers"
                   class="admin-tab <?= $section === 'corporate-officers' ? 'active' : ''; ?>">
                    Corporate Officers
                </a>
                <a href="?section=board-of-administration"
                   class="admin-tab <?= $section === 'board-of-administration' ? 'active' : ''; ?>">
                    Board of Administration
                </a>
                <a href="?section=heads-of-student-services"
                   class="admin-tab <?= $section === 'heads-of-student-services' ? 'active' : ''; ?>">
                    Heads of Student Services
                </a>
            </nav>
        </div>
    </section>

    <!-- Leadership Section -->
    <section class="admin-section">
        <div class="admin-section-inner">

            <div class="admin-section-intro">
                <span class="admin-eyebrow">Leadership &amp; Organization</span>
                <h2><?= htmlspecialchars($current['title']); ?></h2>
                <p><?= htmlspecialchars($current['desc']); ?></p>
            </div>

            <?php if ($section === 'heads-of-student-services'): ?>

                <!-- Flip cards -->
                <div class="hss-grid">
                    <?php foreach ($current['offices'] as $i => $office): ?>
                        <button type="button"
                                class="hss-flip-card"
                                data-hss-flip
                                aria-expanded="false"
                                aria-label="Show services offered by the <?= htmlspecialchars($office['office']); ?>">
                            <div class="hss-flip-card-inner">

                                <!-- FRONT -->
                                <div class="hss-face hss-face-front">
                                    <img src="/assets/images/logos/gnc-logo-v1.svg" class="hss-watermark" aria-hidden="true" alt="">
                                    <h3 class="hss-office-title"><?= htmlspecialchars($office['office']); ?></h3>

                                    <div class="hss-front-body">
                                        <div class="hss-photo">
                                            <img src="/assets/images/<?= htmlspecialchars($office['img']); ?>" alt="<?= htmlspecialchars($office['name']); ?>">
                                        </div>

                                        <ul class="hss-contact-list">
                                            <li>
                                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                                <div>
                                                    <span class="hss-contact-label">Contact Information</span>
                                                    <span class="hss-contact-value"><?= htmlspecialchars($office['contact']['phone']); ?></span>
                                                </div>
                                            </li>
                                            <li>
                                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                                <div>
                                                    <span class="hss-contact-label">Email Address</span>
                                                    <span class="hss-contact-value"><?= htmlspecialchars($office['contact']['email']); ?></span>
                                                </div>
                                            </li>
                                            <li>
                                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                                <div>
                                                    <span class="hss-contact-label">Business Office</span>
                                                    <span class="hss-contact-value"><?= htmlspecialchars($office['contact']['location']); ?></span>
                                                </div>
                                            </li>
                                            <li>
                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                <div>
                                                    <span class="hss-contact-label">Operating Hours</span>
                                                    <span class="hss-contact-value"><?= $office['contact']['hours']; ?></span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="hss-wave" aria-hidden="true">
                                        <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                            <path class="wave-gold" d="M0,80 C150,150 250,30 500,80 L500,150 L0,150 Z"></path>
                                            <path class="wave-maroon" d="M0,100 C150,170 250,50 500,100 L500,150 L0,150 Z"></path>
                                            <path class="wave-green" d="M0,120 C150,190 250,70 500,120 L500,150 L0,150 Z"></path>
                                        </svg>
                                    </div>

                                    <div class="hss-badge">
                                        <span class="hss-badge-title"><?= htmlspecialchars($office['name']); ?></span>
                                        <span class="hss-badge-sub"><?= htmlspecialchars($office['role']); ?></span>
                                    </div>

                                    <span class="hss-flip-hint"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Tap to view services</span>
                                </div>

                                <!-- BACK -->
                                <div class="hss-face hss-face-back">
                                    <img src="/assets/images/logos/gnc-logo-v1.svg" class="hss-watermark" aria-hidden="true" alt="">
                                    <h3 class="hss-services-title">Services Offered</h3>
                                    <ul class="hss-services-list">
                                        <?php foreach ($office['services'] as $service): ?>
                                            <li><i class="bi bi-chevron-right" aria-hidden="true"></i> <?= htmlspecialchars($service); ?></li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <div class="hss-wave" aria-hidden="true">
                                        <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                            <path class="wave-gold" d="M0,80 C150,150 250,30 500,80 L500,150 L0,150 Z"></path>
                                            <path class="wave-maroon" d="M0,100 C150,170 250,50 500,100 L500,150 L0,150 Z"></path>
                                            <path class="wave-green" d="M0,120 C150,190 250,70 500,120 L500,150 L0,150 Z"></path>
                                        </svg>
                                    </div>

                                    <span class="hss-flip-hint"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Tap to go back</span>
                                </div>

                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <!-- Grid layout for Trustees/Administration -->
                <div class="admin-grid">
                    <?php foreach ($current['people'] as $person): ?>
                        <div class="admin-card-wrap skeleton-group">
                            <div class="admin-square-top" aria-hidden="true"></div>

                            <div class="admin-photo-card">
                                <div class="skeleton-wrap" style="height: 100%;">
                                    <img src="/assets/images/<?= htmlspecialchars($person['img']); ?>" alt="<?= htmlspecialchars($person['role']); ?>">
                                </div>

                                <div class="admin-wave" aria-hidden="true">
                                    <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                        <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                        <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                        <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                    </svg>
                                </div>

                                <div class="admin-badge">
                                    <span class="admin-badge-title"><?= htmlspecialchars($person['name']); ?></span>
                                    <span class="admin-badge-sub"><?= htmlspecialchars($person['role']); ?></span>
                                </div>
                            </div>

                            <div class="admin-square-bottom" aria-hidden="true"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

    <script>
        document.querySelectorAll('[data-hss-flip]').forEach(function (card) {
            card.addEventListener('click', function () {
                const isFlipped = card.classList.toggle('is-flipped');
                card.setAttribute('aria-expanded', isFlipped ? 'true' : 'false');
            });
        });
    </script>

</body>
</html>