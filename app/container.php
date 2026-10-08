<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Psr\Container\ContainerInterface;

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);
$containerBuilder->addDefinitions([
    Adapter::class => function () {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => __DIR__ . '/../db/db.sqlite3',
        ]);

        $adapter->query('PRAGMA foreign_keys = ON;')->execute();

        return $adapter;
    },
    'db' => \DI\get(Adapter::class),
    Form::class => function (ContainerInterface $container) {
        $form = new Form();

        $form->rule('unique', function ($value, $param, $field) use ($container) {
            $sql = new Sql($container->get('db'));

            $params = explode(',', $param);

            $table = $params[0] ?? null;
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
]);

return $containerBuilder->build();
