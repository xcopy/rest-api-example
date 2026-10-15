<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;

$definitions = require __DIR__ . '/definitions.php';
$middlewares = require __DIR__ . '/middlewares.php';
$routes = require __DIR__ . '/routes.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAttributes(true);
$containerBuilder->addDefinitions($definitions);
$container = $containerBuilder->build();

AppFactory::setContainer($container);

$app = AppFactory::create();

$container->set(
    RouteParserInterface::class,
    $app->getRouteCollector()->getRouteParser()
);

$routes($app);
$middlewares($app);

return $app;
