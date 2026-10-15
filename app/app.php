<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;

$container = require __DIR__ . '/container.php';

AppFactory::setContainer($container);

$app = AppFactory::create();

$container->set(
    RouteParserInterface::class,
    $app->getRouteCollector()->getRouteParser()
);

$routes = require __DIR__ . '/routes.php';
$routes($app);

$middlewares = require __DIR__ . '/middlewares.php';
$middlewares($app);

return $app;
