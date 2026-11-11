<?php
// Copy to config/secrets.php ON THE HOST and enter the existing production values.
// secrets.php is ignored by Git. Do not upload these placeholder values as credentials.
// Entries here override .env; omit entries that should continue using .env.
return [
    // Uncomment and fill these to use independent Google Gemini credentials.
    // 'CHATBOT_API_KEY' => 'YOUR_CHATBOT_API_KEY',
    // 'PHOTO_CHECK_API_KEY' => 'YOUR_IMAGE_VALIDATION_API_KEY',
    // GEMINI_API_KEY remains the legacy fallback for either omitted key.
    'DB_HOST' => 'YOUR_HOSTED_DATABASE_HOST',
    'DB_NAME' => 'YOUR_HOSTED_DATABASE_NAME',
    'DB_USER' => 'YOUR_HOSTED_DATABASE_USER',
    'DB_PASS' => 'YOUR_EXISTING_DATABASE_PASSWORD',
    'DB_CHARSET' => 'utf8mb4',

    // Enable only after creating this HTTPS subdomain with the SAME document root as the visitor site.
    // 'ADMIN_PWA_ORIGIN' => 'https://admin.gncipampanga.com',
    // When enabled, ADMIN_PWA_ORIGIN also owns password-reset links.
    // Public HTTPS URL for password reset links when ADMIN_PWA_ORIGIN is not configured.
    'AUTH_PUBLIC_URL' => 'https://YOUR_LIVE_DOMAIN',

    // cPanel mailbox SMTP: copy values from Email Accounts > Connect Devices.
    // PHPMailer is the SMTP client library, not the email provider.
    'MAIL_TRANSPORT' => 'smtp',
    'MAIL_HOST' => 'YOUR_CPANEL_MAIL_HOST',
    'MAIL_PORT' => 465,
    'MAIL_ENCRYPTION' => 'ssl',
    'MAIL_USERNAME' => 'YOUR_CPANEL_MAILBOX_ADDRESS',
    'MAIL_PASSWORD' => 'YOUR_CPANEL_MAILBOX_PASSWORD',
    'MAIL_FROM_ADDRESS' => 'no-reply@YOUR_LIVE_DOMAIN',
    'MAIL_FROM_NAME' => 'GNC Admin System',
    // Optional alternative, only when the host provides a local PHP mail service:
    // 'MAIL_TRANSPORT' => 'mail',
];
