<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/basic-education-content.php';

function tuitionFeeIconChoices(): array
{
    return array_filter(academicFeatureIconChoices(), static fn($choice) => !isset($choice['path']));
}

function tuitionFeeIcon(string $key, string $class, array $fields): string
{
    $field = $fields[$key];
    $choices = tuitionFeeIconChoices();
    $choice = $choices[$field['value']] ?? $choices[$field['default']];
    $h = static fn($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $mark = ceManageMark('data-ve-icon="tuition" data-ve-key="' . $h($key) . '" role="button" tabindex="0" aria-label="Change ' . $h($field['label']) . '"');
    $icon = isset($choice['material'])
        ? '<span class="material-symbols-outlined" aria-hidden="true">' . $h($choice['material']) . '</span>'
        : '<i class="bi bi-' . $h($choice['glyph']) . '" aria-hidden="true"></i>';
    return '<span class="' . $h($class) . '"' . $mark . '>' . $icon . '</span>';
}
/** Published tuition PDFs, grouped by their category folder. No sample fee amounts. */
function tuitionFeeFolderCategories(): array
{
    $categories = [
        'college' => ['label' => 'College', 'icon' => 'mortarboard'],
        'graduate-school' => ['label' => 'Graduate School', 'icon' => 'bank'],
        'senior-high-school' => ['label' => 'Senior High School', 'icon' => 'book'],
        'basic-education' => ['label' => 'Basic Education', 'icon' => 'people'],
        'other-fees' => ['label' => 'Other Fees', 'icon' => 'file-earmark-text'],
    ];
    $root = dirname(__DIR__) . '/uploads/tuition-fees';
    foreach ($categories as $key => &$category) {
        $category['documents'] = [];
        foreach (glob($root . '/' . $key . '/*') ?: [] as $file) {
            if (!is_file($file) || is_link($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') continue;
            $name = pathinfo($file, PATHINFO_FILENAME);
            $category['documents'][] = [
                'title' => preg_replace('/[_-]+/', ' ', $name),
                'url' => '/uploads/tuition-fees/' . $key . '/' . rawurlencode(basename($file)),
                'size' => filesize($file) >= 1048576 ? round(filesize($file) / 1048576, 1) . ' MB' : max(1, (int)round(filesize($file) / 1024)) . ' KB',
            ];
        }
        usort($category['documents'], fn($a, $b) => strnatcasecmp($a['title'], $b['title']));
    }
    unset($category);
    return $categories;
}

function tuitionFeeFields(): array
{
    $fields = [
        'hero_title' => ['Tuition and Fees', 'Page title', 255],
        'hero_intro' => ['Find transparent and up-to-date information about the tuition, miscellaneous, and other fees for all programs.', 'Page introduction', 2000],
        'categories_title' => ['Fee Categories', 'Category heading', 100],
        'empty_title' => ['Fee schedule coming soon', 'Empty category heading', 255],
        'empty_intro' => ['No tuition and fee document is available for this category yet. Please contact us for the latest fee information.', 'Empty category description', 2000],
        'contact_label' => ['Contact us', 'Contact link label', 100],
        'download_label' => ['Download PDF', 'Download label', 100],
        'semester_notice' => ['All fees are computed on a per semester basis.', 'Semester notice', 255],
        'treasurer_notice' => ['The specific amounts of tuition and other fee charges are issued by the Treasurer during enrollment.', 'Treasurer notice', 255],
        'payment_title' => ['Payment', 'Payment heading', 100],
        'payment_intro' => ['<p>Installment options are available.</p><p>Bank and online payments are available.</p><p>Unsettled balances may affect enrollment and release of records.</p>', 'Payment information', 2000],
        'promissory_title' => ['Promissory Note', 'Promissory note heading', 100],
        'promissory_intro' => ['Pay at least 50% and settle the balance by the next payment period.', 'Promissory note information', 2000],
        'discounts_title' => ['Discounts', 'Discounts heading', 100],
        'discounts_intro' => ['Eligible siblings receive a 5% tuition discount.', 'Discount information', 2000],
        'refunds_title' => ['Refunds', 'Refunds heading', 100],
        'refunds_intro' => ['Refund charges depend on whether withdrawal occurs during the 1st or 2nd week of the semester.', 'Refund information', 2000],
        'online_payment_title' => ['Pay Your Fees Online', 'Online payment heading', 100],
        'online_payment_intro' => ['Skip the lines and pay your school fees conveniently online. Choose your preferred payment method and follow the step-by-step guide.', 'Online payment information', 2000],
        'online_payment_link' => ['View Online Payment Guide', 'Online payment link label', 100],
    ];
    foreach (tuitionFeeFolderCategories() as $key => $category) $fields['category_' . $key] = [$category['label'], 'Category name', 100];
    foreach ($fields as $key => $field) $fields[$key] = ['value'=>$field[0], 'label'=>$field[1], 'max'=>$field[2]];
    foreach ([
        'semester_icon' => ['bi:info-lg', 'Semester notice icon'],
        'payment_icon' => ['bi:receipt', 'Payment icon'],
        'promissory_icon' => ['bi:journal-check', 'Promissory note icon'],
        'discounts_icon' => ['bi:percent', 'Discounts icon'],
        'refunds_icon' => ['bi:cash-coin', 'Refunds icon'],
        'online_payment_icon' => ['bi:credit-card-2-front', 'Online payment icon'],
    ] as $key => [$icon, $label]) {
        $fields[$key] = ['value'=>$icon, 'default'=>$icon, 'label'=>$label, 'max'=>100, 'type'=>'icon'];
    }
    try {
        foreach (getDB()->query('SELECT field_key,content FROM tuition_fee_content')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($fields[$row['field_key']])) $fields[$row['field_key']]['value'] = $row['content'];
        }
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
    return $fields;
}

function tuitionFeeCategories(): array
{
    $categories = tuitionFeeFolderCategories();
    $fields = tuitionFeeFields();
    foreach ($categories as $key => &$category) $category['label'] = $fields['category_' . $key]['value'];
    unset($category);
    try {
        $rows = getDB()->query('SELECT * FROM tuition_fee_documents ORDER BY document_id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categories as &$category) $category['documents'] = [];
        unset($category);
        foreach ($rows as $row) {
            if (!isset($categories[$row['category_key']])) continue;
            $categories[$row['category_key']]['documents'][] = [
                'id'=>(int)$row['document_id'], 'title'=>$row['title'], 'url'=>$row['file_path'],
                'size'=>$row['file_size'] >= 1048576 ? round($row['file_size']/1048576,1).' MB' : max(1,(int)round($row['file_size']/1024)).' KB',
            ];
        }
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
    return $categories;
}
