<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$app = require APP_BASE_PATH . '/config/app.php';
$phinx = require APP_BASE_PATH . '/config/phinx.php';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'migrations',
        'default_environment' => $app['env'],
        ...$phinx,
    ],
];
