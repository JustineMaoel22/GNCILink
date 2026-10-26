<?php
/**
 * GNCILink AI Assistant - static site knowledge (the ONLY hand-maintained file).
 *
 * Everything dynamic (announcements, events, programs, offices, people,
 * vision/mission) is read from MySQL at question time. This file only holds
 * things that are NOT stored in the database:
 *   1. the program-name fallback list,
 *   2. the page URLs the assistant is allowed to recommend,
 *   3. a few institution-level facts.
 *
 * Edit the URL map below if your routes differ. A page set to null is simply
 * never recommended (the assistant never invents URLs).
 */
declare(strict_types=1);

/**
 * Path prefix when the site is NOT at the domain root (e.g. '/gnc'); '' otherwise.
 * Derived from where the chatbot folder sits under the web root, so links stay correct on any host.
 */
function gncbot_site_prefix(): string
{
    static $prefix = null;
    if ($prefix !== null) { return $prefix; }
    $doc = rtrim(str_replace('\\', '/', (string)realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''))), '/');
    $parent = str_replace('\\', '/', (string)realpath(__DIR__ . '/../..'));
    $prefix = ($doc !== '' && strpos($parent . '/', $doc . '/') === 0) ? rtrim(substr($parent, strlen($doc)), '/') : '';
    return $prefix;
}
function gncbot_url(string $path): string { return gncbot_site_prefix() . $path; }

/** Institution facts. Every value here also appears on the live site/DB. */
function gncbot_institution(): array
{
    return [
        'name'     => 'Guagua National Colleges, Inc. (GNC)',
        // Address as shown in the GNCILink website footer.
        'location' => 'Rep. Eligio Lagman St, Sta. Filomena Rd, Guagua, Pampanga',
        'hours'    => 'Monday - Friday, 8:00 AM - 5:00 PM',   // as shown in the website footer
        'phone'    => '(045) 900-4473',   // same trunk line on every office record
        'email'    => 'info@gnc.edu.ph',  // general inbox used by most offices
        'facebook' => 'https://www.facebook.com/gnc.edu.ph',
    ];
}

/**
 * Pages the assistant may recommend. Only routes that exist in the project
 * are filled in; add the rest (about, vision-mission, contact, administration,
 * student-services ...) when you confirm their paths.
 */
function gncbot_page_urls(): array
{
    return [
        'home'                => ['label' => 'GNCILink Home',                'url' => gncbot_url('/')],
        'developers'          => ['label' => 'Developers Corner',            'url' => gncbot_url('/pages/about/gnci-developers-corner-2/the-devs.php')],
        'events'              => ['label' => 'Calendar of Events',           'url' => gncbot_url('/#events')],
        'news_announcements'  => ['label' => 'News & Announcements',         'url' => gncbot_url('/pages/news-and-announcement.php')],
        'college_departments' => ['label' => 'College Departments',          'url' => gncbot_url('/pages/academics/college/college-dept.php')],
        'basic_education'     => ['label' => 'Basic Education',              'url' => gncbot_url('/pages/academics/basic-edu/basic-edu-dept.php')],
        'graduate_school'     => ['label' => 'Graduate School',              'url' => gncbot_url('/pages/academics/graduate-school/graduate-school.php')],
        'campus_navigator'    => ['label' => 'Campus Navigator',             'url' => gncbot_url('/campus_navigator.html')],
        'facebook'            => ['label' => 'GNC Official Facebook Page',   'url' => 'https://www.facebook.com/gnc.edu.ph'],
        // --- fill these in when the routes are confirmed (null = not recommended) ---
        'vision_mission'      => null,   // e.g. ['label' => 'Vision & Mission', 'url' => gncbot_url('/pages/about/vision-mission.php')]
        'about'               => null,
        'contact'             => null,
        'administration'      => null,
        'student_services'    => null,
    ];
}

/** URL builders for CMS records (patterns taken from index.php). */
function gncbot_announcement_url(string $slug): string { return gncbot_url('/announcement.php?slug=' . rawurlencode($slug)); }
function gncbot_news_url(string $slug): string         { return gncbot_url('/news.php?slug=' . rawurlencode($slug)); }
function gncbot_program_events_url(string $code): string { return gncbot_url('/?program=' . rawurlencode($code) . '#events'); }

/** These suffixes distinguish CMS records; visitors use the strand abbreviation. */
function gncbot_program_display_code(string $code): string {
    return ['ABE2' => 'ABE', 'AE2' => 'AE', 'EHSS2' => 'EHSS', 'HAS2' => 'HAS', 'IDT2' => 'IDT'][$code] ?? $code;
}

/**
 * Program code => full name. The names are not in MySQL (programs only stores
 * the code), so reuse the project's own list when available and otherwise
 * fall back to the identical list used by index.php.
 */
function gncbot_program_names(): array
{
    $fallback = [
        'BEEd'    => 'Bachelor of Elementary Education',
        'BSEd'    => 'Bachelor of Secondary Education',
        'BAEL'    => 'Bachelor of Arts in English Language',
        'BSCE'    => 'Bachelor of Science in Civil Engineering',
        'BSAIS'   => 'Bachelor of Science in Accounting Information Systems',
        'BSA'     => 'Bachelor of Science in Accountancy',
        'BSBA-FM' => 'Bachelor of Science in Business Administration major in Financial Management',
        'BSCS'    => 'Bachelor of Science in Computer Science',
        'BSIT'    => 'Bachelor of Science in Information Technology',
        'BSHM'    => 'Bachelor of Science in Hospitality Management',
        'BSTM'    => 'Bachelor of Science in Tourism Management',
        'BSMLS'   => 'Bachelor of Science in Medical Laboratory Science',
        'BSPh'    => 'Bachelor of Science in Pharmacy',
        'BSN'     => 'Bachelor of Science in Nursing',
    ];

    if (function_exists('getProgramCategories')) {
        try {
            $live = getProgramCategories();
            if (is_array($live)) {
                foreach ($live as $code => $name) {
                    if (is_string($code) && is_string($name) && $code !== 'ALL' && $name !== '') {
                        $fallback[$code] = $name;
                    }
                }
            }
        } catch (Throwable $e) { /* keep fallback */ }
    }
    return $fallback;
}
