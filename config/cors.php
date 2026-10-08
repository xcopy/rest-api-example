<?php

declare(strict_types=1);

return [
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS')))
    ),
    'allowed_methods' => getenv('CORS_ALLOWED_METHODS') ?: 'GET, POST',
    'allowed_headers' => getenv('CORS_ALLOWED_HEADERS') ?: 'Authorization, Content-Type',
    'exposed_headers' => getenv('CORS_EXPOSED_HEADERS') ?: 'RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Retry-After',
    'max_age' => getenv('CORS_MAX_AGE') ?: '600',
];
