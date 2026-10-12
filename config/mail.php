<?php

declare(strict_types=1);

return [
    'dsn' => getenv('MAILER_DSN') ?: 'smtp://localhost:1025',
    'from_address' => getenv('MAILER_FROM_ADDRESS') ?: 'no-reply@example.com',
    'from_name' => getenv('MAILER_FROM_NAME') ?: (getenv('APP_NAME') ?: 'REST API Example'),
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'verification_ttl' => getenv('AUTH_EMAIL_VERIFICATION_TTL') ?: '+24 hours',
    'password_reset_ttl' => getenv('AUTH_PASSWORD_RESET_TTL') ?: '+1 hour',
];
