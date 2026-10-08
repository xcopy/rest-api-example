<?php

declare(strict_types=1);

return [
    'access_token_ttl' => getenv('AUTH_ACCESS_TOKEN_TTL') ?: '+15 minutes',
    // 'refresh_token_ttl' => getenv('AUTH_REFRESH_TOKEN_TTL') ?: '+30 days',
];
