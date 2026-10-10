<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';
$local = $db['local'];
$production = $db['production'];

return [
    'local' => [
        'adapter' => 'sqlite',
        'name' => str_replace('.sqlite3', '', $local['database']),
    ],
    'production' => [
        'adapter' => 'mysql',
        'name' => $production['database'],
        'host' => $production['hostname'],
        'port' => $production['port'],
        'user' => $production['username'],
        'pass' => $production['password'],
        'charset' => $production['charset'],
    ],
];
