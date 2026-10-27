<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-upload.php';
$path = tempnam(sys_get_temp_dir(), 'gnci-pdf-');
try {
    // Build an empty PDF with byte-accurate object offsets and a real cross-reference table.
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ([1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '<< /Type /Pages /Count 0 /Kids [] >>'] as $id => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= "$id 0 obj\n$object\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 3\n0000000000 65535 f \n";
    foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";
    file_put_contents($path, $pdf);
    if (!uploadPdfSignatureValid($path) || imageUploadMime($path) !== 'application/pdf') {
        throw new RuntimeException('Valid PDF rejected.');
    }
    // Incremental revisions may replace the cross-reference table with an xref stream.
    $revised = $pdf;
    $streamOffset = strlen($revised);
    $revised .= "3 0 obj\n<< /Type /XRef /Size 4 /W [1 2 1] /Length 0 /Root 1 0 R >>\nstream\n\nendstream\nendobj\nstartxref\n$streamOffset\n%%EOF\n";
    file_put_contents($path, $revised);
    if (!uploadPdfSignatureValid($path)) throw new RuntimeException('Cross-reference stream rejected.');
    foreach ([
        '<?php echo "not a PDF";',
        "%PDF-1.4\n",
        str_replace("startxref\n$xrefOffset", "startxref\n999999999", $pdf),
        str_replace("startxref\n$xrefOffset", "startxref\n9", $pdf),
        str_replace('%%EOF', '', $pdf),
        $pdf . '<script>not a PDF trailer</script>',
        "garbage\n" . $pdf,
    ] as $invalid) {
        file_put_contents($path, $invalid);
        if (uploadPdfSignatureValid($path)) throw new RuntimeException('Malformed or disguised PDF accepted.');
    }
    echo "PASS: PDF detection, incremental xref stream, and malformed/disguised file rejection.\n";
} finally { unlink($path); }
