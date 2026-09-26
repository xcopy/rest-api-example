<?php

declare(strict_types=1);

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'migrations',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name'    => __DIR__ . '/db/db',
        ],
    ],
];
