<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Predis\Client;
use Predis\ClientInterface;

return [
    Adapter::class => function () {
        $app = require APP_BASE_PATH . '/config/app.php';
        $db = require APP_BASE_PATH . '/config/db.php';

        $adapter = new Adapter($db[$app['env']]);

        if ($app['env'] === 'local') {
            $adapter->query('PRAGMA foreign_keys = ON;')->execute();
        }

        return $adapter;
    },
    ClientInterface::class => function () {
        $config = require APP_BASE_PATH . '/config/redis.php';

        $parameters = [
            'scheme' => 'tcp',
            'host' => $config['host'],
            'port' => $config['port'],
            'timeout' => $config['timeout'],
            'read_write_timeout' => $config['timeout'],
            'database' => $config['database'],
        ];

        if ($config['username'] !== null && $config['username'] !== '') {
            $parameters['username'] = $config['username'];
        }

        if ($config['password'] !== null && $config['password'] !== '') {
            $parameters['password'] = $config['password'];
        }

        return new Client($parameters, [
            'prefix' => $config['prefix'],
        ]);
    },
    Form::class => function (Adapter $db) {
        $form = new Form();

        $form->rule('unique', function ($value, $param, $field) use ($db) {
            $sql = new Sql($db);

            $params = explode(',', $param);

            $table = $params[0];
            $currentUserId = $params[1] ?? null;

            $select = $sql
                ->select($table)
                ->where([$field => $value])
                ->limit(1);

            if ($currentUserId !== null) {
                $select->where(['id != ?' => $currentUserId]);
            }

            $results = $sql->prepareStatementForSqlObject($select)->execute();

            return $results->count() === 0;
        });

        $form->message('unique', 'The value for the field {field} is already taken');

        return $form;
    },
];
