<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

Dotenv::createUnsafeImmutable(BASE_PATH)->load();

$definitions = require __DIR__ . '/definitions.php';
$middlewares = require __DIR__ . '/middlewares.php';
$routes = require __DIR__ . '/routes.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);
$containerBuilder->addDefinitions($definitions);
$container = $containerBuilder->build();

AppFactory::setContainer($container);

$app = AppFactory::create();

$routes($app);
$middlewares($app);

return $app;
