<?php

declare(strict_types=1);

use App\Mailer\FileTransport;
use App\Services\ConfigService;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Predis\Client;
use Predis\ClientInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

return [
    ConfigService::class => function () {
        $values = [];
        $files = glob(APP_BASE_PATH . '/config/*.php');

        if ($files === false) {
            throw new RuntimeException('Unable to load configuration files.');
        }

        foreach ($files as $file) {
            $value = require $file;
            if (!is_array($value)) {
                throw new RuntimeException(sprintf('Configuration file "%s" must return an array.', $file));
            }

            $values[basename($file, '.php')] = $value;
        }

        return new ConfigService($values);
    },
    MailerInterface::class => function (ConfigService $config) {
        $env = $config->get('app.env');
        $transport = in_array($env, ['local', 'testing'])
            ? new FileTransport(APP_BASE_PATH . '/logs/mail')
            : Transport::fromDsn($config->get('mail.dsn'));

        return new Mailer($transport);
    },
    Adapter::class => function (ConfigService $config) {
        $env = $config->get('app.env');
        $adapter = new Adapter($config->get('db.' . $env));

        if (in_array($env, ['local', 'testing'])) {
            $adapter->query('PRAGMA foreign_keys = ON;')->execute();
        }

        return $adapter;
    },
    Sql::class => function (Adapter $db) {
        return new Sql($db);
    },
    ClientInterface::class => function (ConfigService $config) {
        $parameters = [
            'scheme' => 'tcp',
            'host' => $config->get('redis.host'),
            'port' => $config->get('redis.port'),
            'timeout' => $config->get('redis.timeout'),
            'read_write_timeout' => $config->get('redis.timeout'),
            'database' => $config->get('redis.database'),
        ];

        $username = $config->get('redis.username');
        if ($username !== null && $username !== '') {
            $parameters['username'] = $username;
        }

        $password = $config->get('redis.password');
        if ($password !== null && $password !== '') {
            $parameters['password'] = $password;
        }

        return new Client($parameters, [
            'prefix' => $config->get('redis.prefix'),
        ]);
    },
];
