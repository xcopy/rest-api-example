<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Middlewares\AuthenticationMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $controllerClass = AuthController::class;

        $group->post('/login', [$controllerClass, 'login']);
        $group->post('/logout', [$controllerClass, 'logout'])->add(AuthenticationMiddleware::class);
        $group->post('/logout-all', [$controllerClass, 'logoutAll'])->add(AuthenticationMiddleware::class);
    });
};
