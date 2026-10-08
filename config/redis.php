<?php

declare(strict_types=1);

return [
    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('REDIS_PORT') ?: 6379),
    'timeout' => (float) getenv('REDIS_TIMEOUT') ?: 1,
    'database' => (int) getenv('REDIS_DATABASE') ?: 0,
    'prefix' => getenv('REDIS_PREFIX') ?: 'rest-api-example:',
    'username' => getenv('REDIS_USERNAME'),
    'password' => getenv('REDIS_PASSWORD'),
];
