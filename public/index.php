<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

Dotenv::createUnsafeImmutable(BASE_PATH)->load();

$container = require BASE_PATH . '/app/container.php';
$middlewares = require BASE_PATH . '/app/middlewares.php';
$routes = require BASE_PATH . '/app/routes.php';

AppFactory::setContainer($container);

$app = AppFactory::create();

$routes($app);
$middlewares($app);

$app->run();
