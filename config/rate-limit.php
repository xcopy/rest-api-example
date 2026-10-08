<?php

declare(strict_types=1);

return [
    'limit' => (int) getenv('RATE_LIMIT_LIMIT') ?: 60,
    'window' => (int) getenv('RATE_LIMIT_WINDOW') ?: 60,
];
