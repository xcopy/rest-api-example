<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'REST API Example',
    'env' => getenv('APP_ENV') ?: 'local',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8000',
];
