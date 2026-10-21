<?php
require_once __DIR__ . '/payment-guide-images.php';

function paymentGuideFieldDefaults(): array
{
    $plain = fn($value, $label, $max = 255) => ['value' => $value, 'label' => $label, 'max' => $max, 'type' => 'text'];
    $rich = fn($value, $label) => ['value' => richTextNormalize($value), 'label' => $label, 'max' => 4000, 'type' => 'rich'];
    $url = fn($value, $label) => ['value' => $value, 'label' => $label, 'max' => 2048, 'type' => 'url'];
    $fields = [
        'hero_title' => $plain('Online Payment Guide', 'Page title'),
        'hero_intro' => $rich('Pay your school fees through GCash or online banking with our step-by-step guide.', 'Page introduction'),
        'guide_title' => $plain('Online Payment Guide', 'Guide heading'),
        'guide_intro' => $rich("New to paying online? We've got you covered! Check out this quick guide to walk you through our fast and secure payment process.", 'Guide introduction'),
        'payment_title' => $plain('Step 1: Complete Your Online Payment', 'Payment section heading'),
        'payment_intro' => $rich('Choose your preferred digital bank or digital wallet platform to make your payment.', 'Payment section introduction'),
        'account_name_label' => $plain('Account Name:', 'Account name label', 100),
        'account_name' => $plain('Guagua National Colleges, Inc.', 'Account name'),
        'account_number_label' => $plain('Account Number:', 'Account number label', 100),
        'account_number' => $plain('0713-4510-02', 'Account number', 100),
        'verification_title' => $plain('Step 2: Online Verification', 'Verification section heading'),
        'verification_intro' => $rich('Submit your transaction details or proof of transfer for the verification of your payment.', 'Verification introduction'),
        'send_receipt' => $rich('<p>Send the screenshot of your transaction receipt to the <strong>GNC Accounting Office</strong> Facebook Page.</p>', 'Send receipt instructions'),
        'details_intro' => $rich('Include the following details in your message:', 'Required details introduction'),
        'detail_name' => $plain('Complete Name', 'Required detail: name'),
        'detail_course' => $plain('Year Level - Course - Department', 'Required detail: course'),
        'detail_date' => $plain('Date of Payment', 'Required detail: date'),
        'detail_purpose' => $plain('Purpose of Payment', 'Required detail: purpose'),
        'confirmation_intro' => $rich('Wait for their confirmation response.', 'Confirmation instructions'),
        'processing_title' => $plain('Step 3: Processing and Claiming', 'Processing section heading'),
        'processing_intro' => $rich('Visit the school premises and proceed directly to Window 3 for the final processing and releasing of your documents.', 'Processing introduction'),
        'processing_time' => $rich('<p>Allow <strong>one (1) business day</strong> for the bank to process your transaction.</p>', 'Processing time instructions'),
        'claim_location' => $rich('<p>Visit the school, and go straight to <strong>Window 3</strong> to claim your clearance or receipt.</p>', 'Claiming location instructions'),
        'claim_proof' => $rich('<p>Show a <strong>screenshot or printed copy</strong> of your payment confirmation when claiming.</p>', 'Proof for claiming'),
        'help_title' => $plain('Need help with your payment?', 'Help heading'),
        'help_intro' => $rich('Contact us for the latest payment instructions or assistance with a transaction.', 'Help description'),
        'accounting_link_label' => $plain('Visit GNC Accounting Office Facebook Page', 'Accounting button label'),
        'accounting_link_url' => $url('https://www.facebook.com/GNCAccountingOffice', 'Accounting page destination'),
        'contact_link_label' => $plain('Contact Us', 'Contact button label'),
        'contact_link_url' => $url('/pages/contact-us.php', 'Contact destination'),
        'back_link_label' => $plain('View Tuition and Fees', 'Tuition link label'),
        'back_link_url' => $url('/pages/admission/tuition-fees.php', 'Tuition link destination'),
    ];
    foreach (['gcash' => ['Option A: Using GCash App', 'Digital wallet', '<p>Click <strong>Bank Transfer</strong>.</p>'],
              'banking' => ['Option B: Using Online Banking', 'Online banking', '<p>Choose <strong>Local Bank</strong>.</p>']] as $key => $option) {
        $label = $key === 'gcash' ? 'GCash' : 'Online banking';
        $fields[$key . '_title'] = $plain($option[0], $label . ' heading');
        $fields[$key . '_label'] = $plain($option[1], $label . ' illustration caption');
        $fields[$key . '_step_1'] = $rich($option[2], $label . ' step 1');
        $fields[$key . '_step_2'] = $rich('<p>Click <strong>BPI</strong>.</p>', $label . ' step 2');
        $fields[$key . '_step_3'] = $rich('Input the necessary details.', $label . ' step 3');
        $fields[$key . '_step_4'] = $rich('Confirm the details and then transfer the money.', $label . ' step 4');
        $fields[$key . '_step_5'] = $rich('Take a screenshot of your transaction receipt.', $label . ' step 5');
        $fields[$key . '_receipt_note'] = $rich('(Note: Only receipts with “Successful Transfer/Payment” notes are acknowledged.)', $label . ' receipt reminder');
    }
    return $fields;
}

function paymentGuideFields(): array
{
    $fields = paymentGuideFieldDefaults();
    try {
        foreach (getDB()->query("SELECT field_key,content FROM tuition_fee_content WHERE field_key LIKE 'payment_text_%'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = substr($row['field_key'], strlen('payment_text_'));
            if (isset($fields[$key])) $fields[$key]['value'] = $row['content'];
        }
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
    return $fields;
}

function paymentGuideValidUrl(string $url): bool
{
    if ($url === '' || preg_match('/[\s\x00-\x1f\x7f<>"\\\\]/', $url)) return false;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
    return (bool)filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true);
}
