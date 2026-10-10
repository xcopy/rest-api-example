<?php

declare(strict_types=1);

return [
    'local' => [
        'driver' => 'Pdo_Sqlite',
        'database' => dirname(__DIR__) . '/db/db.sqlite3',
    ],
    'production' => [
        'driver' => 'Pdo_Mysql',
        'database' => getenv('MYSQL_DATABASE') ?: 'rest_api_example',
        'hostname' => getenv('MYSQL_HOSTNAME') ?: '127.0.0.1',
        'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
        'username' => getenv('MYSQL_USERNAME') ?: '',
        'password' => getenv('MYSQL_PASSWORD') ?: '',
        'charset' => getenv('MYSQL_CHARSET') ?: 'utf8mb4',
    ],
];
