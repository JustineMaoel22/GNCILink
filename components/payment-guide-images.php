<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

function paymentGuideImageDefaults(): array
{
    return [
        'gcash' => 'GCash payment', 'banking' => 'Online banking',
        'send-receipt' => 'Send payment receipt', 'payment-details' => 'Payment details',
        'confirmation' => 'Confirmation response', 'processing' => 'Bank processing',
        'window-3' => 'Claim at Window 3', 'claim-receipt' => 'Payment confirmation copy',
    ];
}

function paymentGuideImages(): array
{
    $images = [];
    foreach (paymentGuideImageDefaults() as $key => $label) {
        $url = '/assets/images/payment-guide/' . $key . '.svg';
        $images[$key] = ['label' => $label, 'url' => $url, 'default' => $url];
    }
    try {
        $rows = getDB()->query("SELECT field_key,content FROM tuition_fee_content WHERE field_key LIKE 'payment_image_%'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $key = substr($row['field_key'], strlen('payment_image_'));
            if (isset($images[$key]) && preg_match('#^/uploads/payment-guide/[a-f0-9]{32}\.(svg|png|jpg|webp)$#D', $row['content'])) {
                $images[$key]['url'] = $row['content'];
            }
        }
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
    return $images;
}

function paymentGuideIllustration(array $images, string $key, string $class): string
{
    return '<div class="' . ceAttr($class) . '"' . ceManageMark('data-ve-img="payment-guide-image" data-ve-key="' . ceAttr($key) . '" data-ve-label="' . ceAttr($images[$key]['label']) . '"') . '>'
        . '<img src="' . ceAttr($images[$key]['url']) . '" alt="" width="500" height="500" loading="lazy"></div>';
}
