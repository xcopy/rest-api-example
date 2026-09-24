<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use', 'use_trait']
        ],
    ])
    ->setFinder((new Finder())->in(__DIR__))
;
