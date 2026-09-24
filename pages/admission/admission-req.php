<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Requirements</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/admission-req-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="admission-intro">
        <div class="admission-hero">
            <div class="admission-hero-inner">
                <nav class="admission-breadcrumb" aria-label="breadcrumb">
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="admission-current">Admission Requirements</span>
                </nav>
                <h1>Admission Requirements</h1>
                <p>GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school’s quality education and enriching intellectual, social, and spiritual environment.</p>
            </div>
        </div>
    </section>

    <section class="admission-requirements">
        <div class="requirements-inner">

            <div class="requirements-tabs" role="tablist" aria-label="Applicant type">
                <button type="button" class="req-tab is-active" data-target="freshmen" role="tab" aria-selected="true" id="tab-freshmen">
                    Incoming Freshmen
                </button>
                <button type="button" class="req-tab" data-target="transferees" role="tab" aria-selected="false" id="tab-transferees">
                    Transferees
                </button>
            </div>

            <div class="requirements-panels">

                <div class="requirements-copy">

                    <div class="req-panel is-active" id="panel-freshmen" role="tabpanel" aria-labelledby="tab-freshmen">
                        <h2>Incoming Freshmen</h2>
                        <p class="req-intro">All incoming freshmen are required to submit the following documents during the admission process.</p>
                        <ol class="req-list">
                            <li><span class="req-num">01</span><span class="req-text">Original Form 138 (Report Card).</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">02</span><span class="req-text">Certificate of Good Moral Character signed by the Principal or Guidance Counselor.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">03</span><span class="req-text">Photocopy of PSA Birth Certificate.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">04</span><span class="req-text">Photocopy of Marriage Certificate (if applicable).</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">05</span><span class="req-text">Medical Certificate.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">06</span><span class="req-text">Three (3) pieces of your recent 2×2 ID photo.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">07</span><span class="req-text">Accomplished Student Profile Form.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">08</span><span class="req-text">Test Result from the Guidance Testing Center.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                        </ol>
                    </div>

                    <div class="req-panel" id="panel-transferees" role="tabpanel" aria-labelledby="tab-transferees" hidden>
                        <h2>Transferees</h2>
                        <p class="req-intro">Transferees must submit the following documents, in addition to their transfer credentials, during the admission process.</p>
                        <ol class="req-list">
                            <li><span class="req-num">01</span><span class="req-text">Honorable Dismissal or Transer Credentials.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">02</span><span class="req-text">Successful Interview with the Dean..</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">03</span><span class="req-text">Certified True Copy of Grades (From Previous School).</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">04</span><span class="req-text">Certificate of Good Moral Character signed by the Principal or Guidance Counselor.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">05</span><span class="req-text">Photocopy of PSA Birth Certificate.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">06</span><span class="req-text">Photocopy of PSA Marriage Certificate (If Applicable)..</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                            <li><span class="req-num">07</span><span class="req-text">Three (3) pieces of your recent 2×2 ID photo.</span><i class="bi bi-check2-square req-check" aria-hidden="true"></i></li>
                        </ol>
                    </div>

                </div>

                <div class="requirements-art">
                    <img src="/assets/images/svg/college admission-cuate 1.svg">
                </div>

            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.req-tab');
            var panels = document.querySelectorAll('.req-panel');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var target = tab.getAttribute('data-target');

                    tabs.forEach(function (t) {
                        t.classList.remove('is-active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    tab.classList.add('is-active');
                    tab.setAttribute('aria-selected', 'true');

                    panels.forEach(function (panel) {
                        var isTarget = panel.id === 'panel-' + target;
                        panel.classList.toggle('is-active', isTarget);
                        panel.hidden = !isTarget;
                    });
                });
            });
        })();
    </script>

</body>
</html>