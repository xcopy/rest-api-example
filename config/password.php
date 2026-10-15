<?php

declare(strict_types=1);

return [
    'reset_ttl' => getenv('PASSWORD_RESET_TTL') ?: '+1 hour',
];
