<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\UserController;
use App\Middleware\JsonResponseMiddleware;
use App\Middleware\JsonRequestMiddleware;
use DI\ContainerBuilder;
use Laminas\Db\Adapter\Adapter;
use Middlewares\TrailingSlash;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Routing\RouteCollectorProxy;

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
    'db' => \DI\get(Adapter::class)
]);

AppFactory::setContainer($containerBuilder->build());

$app = AppFactory::create();
$app->add(new JsonRequestMiddleware());
$app->addRoutingMiddleware();
$app->add(new TrailingSlash(false));
$app->add(new ContentLengthMiddleware());
$app->add(new JsonResponseMiddleware());
$app->addErrorMiddleware(true, true, true);

$app->group('/users', function (RouteCollectorProxy $group) {
    $group->get('', [UserController::class, 'index']);
    $group->get('/{id}', [UserController::class, 'show']);
});

$app->run();
