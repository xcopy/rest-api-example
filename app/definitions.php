<?php

declare(strict_types=1);

use App\Mailer\FileTransport;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Predis\Client;
use Predis\ClientInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

return [
    MailerInterface::class => function () {
        $transport = in_array(config('app.env'), ['local', 'testing'])
            ? new FileTransport(APP_BASE_PATH . '/logs/mail')
            : Transport::fromDsn(config('mail.dsn'));

        return new Mailer($transport);
    },
    Adapter::class => function () {
        $env = config('app.env');
        $adapter = new Adapter(config('db.' . $env));

        if (in_array($env, ['local', 'testing'])) {
            $adapter->query('PRAGMA foreign_keys = ON;')->execute();
        }

        return $adapter;
    },
    Sql::class => function (Adapter $db) {
        return new Sql($db);
    },
    ClientInterface::class => function () {
        $config = config('redis');
        $parameters = [
            'scheme' => 'tcp',
            'host' => $config['host'],
            'port' => $config['port'],
            'timeout' => $config['timeout'],
            'read_write_timeout' => $config['timeout'],
            'database' => $config['databse'],
        ];

        $username = $config['username'];
        if ($username !== null && $username !== '') {
            $parameters['username'] = $username;
        }

        $password = $config['password'];
        if ($password !== null && $password !== '') {
            $parameters['password'] = $password;
        }

        return new Client($parameters, [
            'prefix' => $config['prefix'],
        ]);
    },
];
