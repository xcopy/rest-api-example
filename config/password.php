<?php

declare(strict_types=1);

return [
    'reset_ttl' => getenv('EMAIL_VERIFICATION_TTL') ?: '+24 hours',
];
