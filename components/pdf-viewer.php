<?php
/**
 * GNC – PDF viewer component (public site)
 */

function renderDepartmentPdfViewer(?array $dept, array $opts = []): void {
    $url = $dept['pdf_url'] ?? null;
    if (!$url) return;

    $opts += [
        'title'         => $dept['dept_name'] ?? '',
        'download_name' => $dept['pdf_name'] ?? null,
    ];
    renderPdfViewer($url, $opts);
}

function renderPdfViewer(?string $pdfUrl, array $opts = []): void {
    static $assetsPrinted = false;

    // Same-site upload paths only
    if (!$pdfUrl || strpos($pdfUrl, '/uploads/') !== 0 || strpos($pdfUrl, '..') !== false) {
        return;
    }

    $dl = trim((string)($opts['download_name'] ?? ''));
    if ($dl === '') $dl = basename($pdfUrl);
    if (!preg_match('/\.pdf$/i', $dl)) $dl .= '.pdf';

    $title = trim((string)($opts['title'] ?? ''));
    if ($title === '') $title = preg_replace('/\.pdf$/i', '', $dl);

    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    // Material-style icons
    $icon = function (string $path) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $path . '"/></svg>';
    };
    $ICONS = [
        'menu'     => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
        'fit'      => 'M17 4h3c1.1 0 2 .9 2 2v2h-2V6h-3V4zM4 8V6h3V4H4c-1.1 0-2 .9-2 2v2h2zm16 8v2h-3v2h3c1.1 0 2-.9 2-2v-2h-2zM7 18H4v-2H2v2c0 1.1.9 2 2 2h3v-2zM18 8H6v8h12V8z',
        'rotate'   => 'M7.11 8.53L5.7 7.11C4.8 8.27 4.24 9.61 4.07 11h2.02c.14-.87.49-1.72 1.02-2.47zM6.09 13H4.07c.17 1.39.72 2.73 1.62 3.89l1.41-1.42c-.52-.75-.87-1.59-1.01-2.47zm1.01 5.32c1.16.9 2.51 1.44 3.9 1.61V17.9c-.87-.15-1.71-.49-2.46-1.03L7.1 18.32zM13 4.07V1L8.45 5.55 13 10V6.09c2.84.48 5 2.94 5 5.91s-2.16 5.43-5 5.91v2.02c3.95-.49 7-3.85 7-7.93s-3.05-7.44-7-7.93z',
        'download' => 'M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z',
        'print'    => 'M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z',
        'more'     => 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
    ];

    if (!$assetsPrinted) {
            $assetsPrinted = true;
            echo '<link rel="stylesheet" href="/assets/css/pdf-viewer-style.css?v=2">' . "\n";
            echo '<script src="/assets/js/pdf-viewer.js" defer></script>' . "\n";
        }
    ?>
<section class="gnc-pdf-viewer" data-pdf-src="<?= $e($pdfUrl) ?>" aria-label="<?= $e($title) ?> (PDF)">
    <div class="gnc-pdf-toolbar" role="toolbar" aria-label="PDF controls">

        <!-- LEFT SIDE: Menu and Title -->
        <div class="gnc-pdf-left">
            <button type="button" class="gnc-pdf-icon" data-act="sidebar" aria-label="Toggle page thumbnails" aria-pressed="false" title="Thumbnails"><?= $icon($ICONS['menu']) ?></button>
            <span class="gnc-pdf-title" title="<?= $e($title) ?>"><?= $e($title) ?></span>
        </div>

        <!-- CENTER SIDE: Pagination, Zoom, Tools -->
        <div class="gnc-pdf-center">
            <span class="gnc-pdf-pageinfo">
                <input type="text" inputmode="numeric" value="1" data-role="page-input" aria-label="Page number" disabled>
                <span class="gnc-pdf-of">/</span>
                <span data-role="page-count">–</span>
            </span>
            <span class="gnc-pdf-sep" aria-hidden="true"></span>
            <span class="gnc-pdf-zoom">
                <button type="button" class="gnc-pdf-step" data-act="zoom-out" aria-label="Zoom out" title="Zoom out" disabled>&minus;</button>
                <input type="text" inputmode="numeric" value="100%" data-role="zoom-input" aria-label="Zoom percentage">
                <button type="button" class="gnc-pdf-step" data-act="zoom-in" aria-label="Zoom in" title="Zoom in" disabled>+</button>
            </span>
            <span class="gnc-pdf-sep" aria-hidden="true"></span>
            <span class="gnc-pdf-tools">
                <button type="button" class="gnc-pdf-icon" data-act="fit" aria-label="Fit to page" title="Fit to page" disabled><?= $icon($ICONS['fit']) ?></button>
                <button type="button" class="gnc-pdf-icon" data-act="rotate" aria-label="Rotate counterclockwise" title="Rotate counterclockwise" disabled><?= $icon($ICONS['rotate']) ?></button>
            </span>
        </div>

        <!-- RIGHT SIDE: Download, Print, Menu -->
        <div class="gnc-pdf-right">
            <a class="gnc-pdf-icon" href="<?= $e($pdfUrl) ?>" download="<?= $e($dl) ?>" aria-label="Download" title="Download"><?= $icon($ICONS['download']) ?></a>
            <button type="button" class="gnc-pdf-icon" data-act="print" aria-label="Print" title="Print" disabled><?= $icon($ICONS['print']) ?></button>
            <span class="gnc-pdf-menuwrap">
                <button type="button" class="gnc-pdf-icon" data-act="more" aria-label="More actions" aria-haspopup="true" aria-expanded="false" title="More actions" disabled><?= $icon($ICONS['more']) ?></button>
                <div class="gnc-pdf-menu" data-role="menu" hidden>
                    <a href="<?= $e($pdfUrl) ?>" target="_blank" rel="noopener">Open in new tab</a>
                    <button type="button" data-act="rotate-cw">Rotate clockwise</button>
                </div>
            </span>
        </div>
    </div>

    <div class="gnc-pdf-body">
        <aside class="gnc-pdf-sidebar" data-role="sidebar" aria-label="Page thumbnails">
            <div class="gnc-pdf-thumbs" data-role="thumbs"></div>
        </aside>
        <div class="gnc-pdf-stage" data-role="stage" tabindex="0" aria-label="PDF pages. Scroll to read; use the arrow keys or the page box to change page.">
            <div class="gnc-pdf-pages" data-role="pages"></div>
        </div>
        <div class="gnc-pdf-status" data-role="status" role="status">Loading PDF…</div>
    </div>

    <noscript>
        <p class="gnc-pdf-fallback">
            JavaScript is needed to view this PDF on the page.
            <a href="<?= $e($pdfUrl) ?>" target="_blank" rel="noopener">Open the PDF</a>
        </p>
    </noscript>
</section>
    <?php
}