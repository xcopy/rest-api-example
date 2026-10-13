<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';
$production = $db['production'];

$sqlite = fn(string $env) => [
    'adapter' => 'sqlite',
    'name' => str_replace('.sqlite3', '', $db[$env]['database']),
];

return [
    'local' => $sqlite('local'),
    'testing' => $sqlite('testing'),
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
