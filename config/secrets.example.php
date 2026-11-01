<?php
// Copy to config/secrets.php ON THE HOST and enter the existing production values.
// secrets.php is ignored by Git. Do not upload these placeholder values as credentials.
// Entries here override .env; omit entries that should continue using .env.
return [
    'DB_HOST' => 'YOUR_HOSTED_DATABASE_HOST',
    'DB_NAME' => 'YOUR_HOSTED_DATABASE_NAME',
    'DB_USER' => 'YOUR_HOSTED_DATABASE_USER',
    'DB_PASS' => 'YOUR_EXISTING_DATABASE_PASSWORD',
    'DB_CHARSET' => 'utf8mb4',

    // Public HTTPS URL for password reset links on the live host (e.g. 'https://gncipampanga.com')
    'AUTH_PUBLIC_URL' => 'https://YOUR_LIVE_DOMAIN',

    // Email (PHPMailer SMTP)
    'MAIL_HOST' => 'smtp.gmail.com',
    'MAIL_PORT' => 587,
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_USERNAME' => 'YOUR_SMTP_EMAIL',
    'MAIL_PASSWORD' => 'YOUR_16_CHAR_GMAIL_APP_PASSWORD',
    'MAIL_FROM_NAME' => 'GNC Admin System',
];
